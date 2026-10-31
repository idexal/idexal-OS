/**
 * Real-time synchronization service between web platform and desktop app
 * Implements WebSocket-based live sync for providers, models, and configurations
 */

import { createIdexalOsServerClient } from "../../../app/lib/idexal-os-server";
import { resolveIdexalOsConnection } from "../../shell/idexal-os-connection";
import type { DenDesktopConfig } from "../../../app/lib/den";

export type SyncEventType = 
  | "provider-added"
  | "provider-updated" 
  | "provider-deleted"
  | "model-added"
  | "model-updated"
  | "model-deleted"
  | "plan-updated"
  | "credential-updated"
  | "config-changed";

export type SyncEvent = {
  type: SyncEventType;
  timestamp: number;
  payload: unknown;
  organizationId: string;
};

export type SyncConnectionState = 
  | "disconnected"
  | "connecting"
  | "connected"
  | "reconnecting"
  | "error";

export class RealtimeSyncService {
  private ws: WebSocket | null = null;
  private reconnectTimeout: ReturnType<typeof setTimeout> | null = null;
  private state: SyncConnectionState = "disconnected";
  private messageHandlers: Map<string, Set<(event: SyncEvent) => void>> = new Map();
  private heartbeatInterval: ReturnType<typeof setInterval> | null = null;
  private lastPong: number = Date.now();
  private reconnectAttempts: number = 0;
  private maxReconnectAttempts: number = 5;
  private reconnectDelay: number = 1000; // 1 second initial delay

  constructor() {
    this.state = "disconnected";
  }

  async connect(organizationId: string): Promise<void> {
    if (this.state === "connected" || this.state === "connecting") {
      return;
    }

    this.setState("connecting");
    
    try {
      const connection = await resolveIdexalOsConnection();
      if (!connection.normalizedBaseUrl) {
        throw new Error("No base URL available for connection");
      }

      const clientId = `desktop-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
      const url = `${connection.normalizedBaseUrl.replace(/^https?:\/\//, 'ws://').replace(/\/$/, '')}/sync/${encodeURIComponent(organizationId)}?clientId=${clientId}`;
      
      console.log(`[RealtimeSync] Connecting to ${url}`);
      this.ws = new WebSocket(url);

      this.ws.onopen = () => {
        console.log(`[RealtimeSync] Connection established`);
        this.setState("connected");
        this.reconnectAttempts = 0;
        this.startHeartbeat();
      };

      this.ws.onmessage = (event) => {
        this.handleMessage(event.data);
        this.lastPong = Date.now();
      };

      this.ws.onerror = (error) => {
        console.error(`[RealtimeSync] WebSocket error:`, error);
        this.setState("error");
      };

      this.ws.onclose = () => {
        console.log(`[RealtimeSync] Connection closed`);
        this.stopHeartbeat();
        this.setState("disconnected");
        this.scheduleReconnect(organizationId);
      };

    } catch (error) {
      console.error(`[RealtimeSync] Connection failed:`, error);
      this.setState("error");
      this.scheduleReconnect(organizationId);
    }
  }

  private handleMessage(data: string): void {
    try {
      const event: SyncEvent = JSON.parse(data);
      console.log(`[RealtimeSync] Received event:`, event.type);

      const handlers = this.messageHandlers.get(event.type) || new Set();
      handlers.forEach(handler => handler(event));
      
      // Also broadcast to all handlers for generic events
      const allHandlers = this.messageHandlers.get("*") || new Set();
      allHandlers.forEach(handler => handler(event));
      
    } catch (error) {
      console.error(`[RealtimeSync] Failed to parse message:`, error);
    }
  }

  private scheduleReconnect(organizationId: string): void {
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
    }

    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1); // Exponential backoff
      
      console.log(`[RealtimeSync] Will reconnect in ${delay}ms (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
      
      this.reconnectTimeout = setTimeout(() => {
        this.connect(organizationId);
      }, delay);
    } else {
      console.error(`[RealtimeSync] Max reconnect attempts reached`);
    }
  }

  private startHeartbeat(): void {
    this.heartbeatInterval = setInterval(() => {
      if (this.ws && this.ws.readyState === WebSocket.OPEN) {
        const elapsed = Date.now() - this.lastPong;
        if (elapsed > 30000) { // 30 seconds timeout
          console.warn(`[RealtimeSync] Heartbeat timeout, closing connection`);
          this.ws.close();
        } else {
          this.ws.send(JSON.stringify({ type: "ping" }));
        }
      }
    }, 15000); // Ping every 15 seconds
  }

  private stopHeartbeat(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
  }

  on(eventType: SyncEventType | "*", handler: (event: SyncEvent) => void): void {
    if (!this.messageHandlers.has(eventType)) {
      this.messageHandlers.set(eventType, new Set());
    }
    this.messageHandlers.get(eventType)!.add(handler);
  }

  off(eventType: SyncEventType | "*", handler: (event: SyncEvent) => void): void {
    const handlers = this.messageHandlers.get(eventType);
    if (handlers) {
      handlers.delete(handler);
    }
  }

  getState(): SyncConnectionState {
    return this.state;
  }

  disconnect(): void {
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
      this.reconnectTimeout = null;
    }
    
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }

    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    
    this.setState("disconnected");
  }

  private setState(newState: SyncConnectionState): void {
    const oldState = this.state;
    this.state = newState;
    console.log(`[RealtimeSync] State changed: ${oldState} -> ${newState}`);
  }

  static broadcastConfigUpdate(config: DenDesktopConfig, organizationId: string): void {
    // This would be called by the server to broadcast to all connected clients
    // Implementation depends on backend WebSocket gateway
    console.log(`[RealtimeSync] Broadcasting config update:`, config, organizationId);
  }
}

// Singleton instance
export const realtimeSyncService = new RealtimeSyncService();
