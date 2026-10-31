import { randomBytes } from "node:crypto";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";

const STATE_VERSION = 1;
const MAX_PROFILES = 100;
const KINDS = new Set(["static_residential", "rotating_residential", "datacenter", "mobile", "custom"]);
const PROTOCOLS = new Set(["http", "https", "socks5"]);

/** @typedef {import("@idexal/types/desktop-ipc").ProxyProfileInput} ProxyProfileInput */
/** @typedef {import("@idexal/types/desktop-ipc").ProxyProfile} ProxyProfile */
/** @typedef {import("@idexal/types/desktop-ipc").ProxyProfileKind} ProxyProfileKind */
/** @typedef {import("@idexal/types/desktop-ipc").ProxyProtocol} ProxyProtocol */

/**
 * Encrypted, main-process-only storage for proxy profiles. The renderer never
 * receives a password after a save; it only learns whether one exists.
 */
export class ProxyStore {
  /**
   * @param {{
   *   dirPath: string,
   *   safeStorage?: { isEncryptionAvailable?: () => boolean, encryptString?: (value: string) => Buffer, decryptString?: (value: Buffer) => string } | null,
   *   now?: () => Date,
   * }} options
   */
  constructor({ dirPath, safeStorage = null, now = () => new Date() }) {
    this.dirPath = dirPath;
    this.filePath = path.join(dirPath, "proxy-manager.json");
    this.usageFilePath = path.join(dirPath, "proxy-usage.json");
    this.safeStorage = safeStorage;
    this.now = now;
    /** @type {Array<ProxyProfile & { password: string, passwordCipher: string }>} */
    this.profiles = [];
    this.activeId = null;
    this.enabled = false;
    this.lastError = null;
    this.updatedAt = now().toISOString();
    this.secretsUnavailable = false;
    this.usageMonth = this.#currentMonth();
    this.usageBytes = 0;
  }

  load() {
    this.loadUsage();
    if (!existsSync(this.filePath)) return this;
    try {
      const parsed = JSON.parse(readFileSync(this.filePath, "utf8"));
      if (!parsed || typeof parsed !== "object") return this;
      const profiles = Array.isArray(parsed.profiles) ? parsed.profiles : [];
      this.profiles = profiles
        .map((entry) => this.#hydrate(entry))
        .filter((entry) => entry !== null)
        .slice(0, MAX_PROFILES);
      this.activeId = typeof parsed.activeId === "string" && this.profiles.some((p) => p.id === parsed.activeId)
        ? parsed.activeId
        : null;
      this.enabled = parsed.enabled === true && this.activeId !== null;
      this.updatedAt = typeof parsed.updatedAt === "string" ? parsed.updatedAt : this.updatedAt;
    } catch (error) {
      this.lastError = error instanceof Error ? error.message : "Could not read proxy settings";
    }
    return this;
  }

  /**
   * @returns {import("@idexal/types/desktop-ipc").ProxyState}
   */
  publicState(guard = null) {
    return {
      enabled: this.enabled,
      activeId: this.activeId,
      profiles: this.profiles.map((profile) => this.#publicProfile(profile)),
      guard,
      lastError: this.lastError,
      secretsUnavailable: this.secretsUnavailable,
      updatedAt: this.updatedAt,
    };
  }

  /**
   * @param {string} id
   * @returns {ProxyProfile | null}
   */
  findPublic(id) {
    const profile = this.profiles.find((entry) => entry.id === id);
    return profile ? this.#publicProfile(profile) : null;
  }

  /**
   * @param {string} id
   * @returns {(ProxyProfile & { password: string }) | null}
   */
  findSecret(id) {
    return this.profiles.find((entry) => entry.id === id) ?? null;
  }

  activeProfile() {
    if (!this.activeId) return null;
    return this.profiles.find((entry) => entry.id === this.activeId) ?? null;
  }

  /**
   * @param {ProxyProfileInput} input
   * @returns {ProxyProfile}
   */
  save(input) {
    const rawHost = String(input?.host ?? "");
    const addressHasScheme = /^[a-z][a-z0-9+.-]*:\/\//i.test(rawHost.trim());
    const parsedAddress = splitProxyAddress(rawHost);
    const mergedInput = {
      ...input,
      host: parsedAddress.host || input?.host,
      port: addressHasScheme
        ? (parsedAddress.port ?? input?.port)
        : (input?.port ?? parsedAddress.port),
      protocol: /** @type {ProxyProtocol} */ (addressHasScheme
        ? (parsedAddress.protocol || input?.protocol)
        : (input?.protocol || parsedAddress.protocol || "http")),
      username: addressHasScheme
        ? (parsedAddress.username || input?.username || "")
        : (input?.username || parsedAddress.username || ""),
      password: addressHasScheme
        ? (parsedAddress.password || input?.password || "")
        : (input?.password || parsedAddress.password || ""),
    };
    const normalized = normalizeProfileInput(
      mergedInput,
      (name) => this.profiles.some((p) => p.name === name && p.id !== input?.id),
    );
    const existing = input?.id ? this.profiles.find((entry) => entry.id === input.id) : null;
    if (!existing && this.profiles.length >= MAX_PROFILES) {
      throw new Error(`You can keep at most ${MAX_PROFILES} proxy profiles on this computer.`);
    }

    const timestamp = this.now().toISOString();
    const password = normalizeSecret(mergedInput.password) || existing?.password || "";
    /** @type {ProxyProfile & { password: string, passwordCipher: string }} */
    const profile = {
      ...normalized,
      id: existing?.id ?? `px_${randomBytes(10).toString("hex")}`,
      createdAt: existing?.createdAt ?? timestamp,
      updatedAt: timestamp,
      hasPassword: Boolean(password),
      password,
      passwordCipher: "",
    };

    if (existing) {
      const index = this.profiles.indexOf(existing);
      this.profiles[index] = profile;
    } else {
      this.profiles.push(profile);
    }

    if (this.activeId === null && this.profiles.length === 1) {
      this.activeId = profile.id;
    }
    if (input?.id && this.activeId === input.id) {
      this.enabled = false;
    }

    this.updatedAt = timestamp;
    this.lastError = null;
    this.persist();
    return this.#publicProfile(profile);
  }

  /**
   * @param {string} id
   */
  remove(id) {
    const before = this.profiles.length;
    this.profiles = this.profiles.filter((entry) => entry.id !== id);
    if (this.profiles.length === before) {
      throw new Error("Proxy profile not found.");
    }
    if (this.activeId === id) {
      this.activeId = null;
      this.enabled = false;
    }
    this.updatedAt = this.now().toISOString();
    this.persist();
  }

  /**
   * @param {string} id
   */
  setActive(id) {
    if (!this.profiles.some((entry) => entry.id === id)) {
      throw new Error("Proxy profile not found.");
    }
    this.activeId = id;
    this.enabled = true;
    this.updatedAt = this.now().toISOString();
    this.persist();
  }

  disable() {
    this.enabled = false;
    this.updatedAt = this.now().toISOString();
    this.persist();
  }

  /**
   * @param {string} message
   */
  setError(message) {
    this.lastError = message || null;
    this.updatedAt = this.now().toISOString();
    this.persist();
  }

  persist() {
    mkdirSync(this.dirPath, { recursive: true });
    const payload = {
      version: STATE_VERSION,
      enabled: this.enabled,
      activeId: this.activeId,
      updatedAt: this.updatedAt,
      profiles: this.profiles.map((profile) => ({
        ...this.#publicProfile(profile),
        passwordCipher: this.#encrypt(profile.password),
      })),
    };
    writeFileSync(this.filePath, `${JSON.stringify(payload, null, 2)}\n`, { encoding: "utf8", mode: 0o600 });
  }

  loadUsage() {
    this.usageMonth = this.#currentMonth();
    this.usageBytes = 0;
    if (!existsSync(this.usageFilePath)) return this;
    try {
      const parsed = JSON.parse(readFileSync(this.usageFilePath, "utf8"));
      if (parsed?.month === this.usageMonth && Number.isFinite(parsed.bytes)) {
        this.usageBytes = Math.max(0, Math.trunc(parsed.bytes));
      } else {
        this.persistUsage();
      }
    } catch {
      // A corrupt counter must never block the proxy itself.
    }
    return this;
  }

  usage() {
    if (this.usageMonth !== this.#currentMonth()) {
      this.usageMonth = this.#currentMonth();
      this.usageBytes = 0;
      this.persistUsage();
    }
    return { month: this.usageMonth, bytes: this.usageBytes };
  }

  /**
   * @param {number} bytes
   */
  setUsage(bytes) {
    this.usageMonth = this.#currentMonth();
    this.usageBytes = Math.max(0, Math.trunc(Number(bytes) || 0));
    this.persistUsage();
    return this.usage();
  }

  persistUsage() {
    mkdirSync(this.dirPath, { recursive: true });
    writeFileSync(
      this.usageFilePath,
      `${JSON.stringify({ month: this.usageMonth, bytes: this.usageBytes })}\n`,
      { encoding: "utf8", mode: 0o600 },
    );
  }

  #currentMonth() {
    return this.now().toISOString().slice(0, 7);
  }

  /**
   * @param {Record<string, unknown>} entry
   * @returns {(ProxyProfile & { password: string, passwordCipher: string }) | null}
   */
  #hydrate(entry) {
    try {
      const normalized = normalizeProfileInput({
        id: String(entry.id ?? ""),
        name: String(entry.name ?? ""),
        kind: /** @type {ProxyProfileKind} */ (String(entry.kind ?? "custom")),
        protocol: /** @type {ProxyProtocol} */ (String(entry.protocol ?? "http")),
        host: String(entry.host ?? ""),
        port: Number(entry.port),
        username: String(entry.username ?? ""),
        country: String(entry.country ?? ""),
        timezone: String(entry.timezone ?? ""),
        locale: String(entry.locale ?? ""),
        latitude: typeof entry.latitude === "number" ? entry.latitude : null,
        longitude: typeof entry.longitude === "number" ? entry.longitude : null,
        userAgent: String(entry.userAgent ?? ""),
        rotationUrl: String(entry.rotationUrl ?? ""),
        providerId: String(entry.providerId ?? ""),
        providerSlug: String(entry.providerSlug ?? ""),
        notes: String(entry.notes ?? ""),
        applyToApp: entry.applyToApp !== false,
        alignIdentity: entry.alignIdentity !== false,
      });
      const cipher = String(entry.passwordCipher ?? "");
      if (cipher.startsWith("raw:v1:")) {
        this.secretsUnavailable = true;
      }
      const password = this.#decrypt(cipher);
      return {
        ...normalized,
        id: typeof entry.id === "string" && entry.id ? entry.id : `px_${randomBytes(10).toString("hex")}`,
        createdAt: typeof entry.createdAt === "string" ? entry.createdAt : this.updatedAt,
        updatedAt: typeof entry.updatedAt === "string" ? entry.updatedAt : this.updatedAt,
        hasPassword: Boolean(password),
        password,
        passwordCipher: cipher,
      };
    } catch {
      return null;
    }
  }

  /**
   * @param {ProxyProfile & { password: string }} profile
   * @returns {ProxyProfile}
   */
  #publicProfile(profile) {
    return {
      id: profile.id,
      name: profile.name,
      kind: profile.kind,
      protocol: profile.protocol,
      host: profile.host,
      port: profile.port,
      username: profile.username,
      hasPassword: Boolean(profile.password),
      country: profile.country,
      timezone: profile.timezone,
      locale: profile.locale,
      latitude: profile.latitude,
      longitude: profile.longitude,
      userAgent: profile.userAgent,
      rotationUrl: profile.rotationUrl,
      providerId: profile.providerId,
      providerSlug: profile.providerSlug,
      notes: profile.notes,
      applyToApp: profile.applyToApp,
      alignIdentity: profile.alignIdentity,
      createdAt: profile.createdAt,
      updatedAt: profile.updatedAt,
    };
  }

  /**
   * @param {string} value
   */
  #encrypt(value) {
    if (!value) return "";
    const safeStorage = this.safeStorage;
    if (safeStorage?.isEncryptionAvailable?.()) {
      try {
        return `enc:v1:${safeStorage.encryptString(value).toString("base64")}`;
      } catch {
        // Fall through to the explicit raw format.
      }
    }
    this.secretsUnavailable = true;
    return `raw:v1:${Buffer.from(value, "utf8").toString("base64")}`;
  }

  /**
   * @param {string} cipher
   */
  #decrypt(cipher) {
    if (!cipher) return "";
    if (cipher.startsWith("raw:v1:")) {
      try {
        return Buffer.from(cipher.slice("raw:v1:".length), "base64").toString("utf8");
      } catch {
        return "";
      }
    }
    if (cipher.startsWith("enc:v1:")) {
      const safeStorage = this.safeStorage;
      if (!safeStorage?.decryptString) return "";
      try {
        return safeStorage.decryptString(Buffer.from(cipher.slice("enc:v1:".length), "base64"));
      } catch {
        this.lastError = "The saved proxy password could not be decrypted on this computer. Enter it again.";
        return "";
      }
    }
    return "";
  }
}

/**
 * @param {Partial<ProxyProfileInput>} input
 * @param {(name: string) => boolean} [nameExists]
 * @returns {Omit<ProxyProfile, "id" | "hasPassword" | "createdAt" | "updatedAt">}
 */
export function normalizeProfileInput(input, nameExists = () => false) {
  const name = String(input?.name ?? "").trim();
  if (!name || name.length > 80) throw new Error("Give the proxy a name of 80 characters or fewer.");
  if (nameExists(name)) throw new Error("A proxy with this name already exists.");

  const kind = String(input?.kind ?? "custom");
  if (!KINDS.has(kind)) throw new Error("Choose a valid proxy type.");

  const protocol = String(input?.protocol ?? "http").toLowerCase();
  if (!PROTOCOLS.has(protocol)) throw new Error("Choose HTTP, HTTPS, or SOCKS5.");

  const parsed = splitProxyAddress(String(input?.host ?? ""));
  const host = normalizeHost(parsed.host);
  if (!host) throw new Error("Enter the proxy host.");
  const port = Number(parsed.port ?? input?.port);
  if (!Number.isInteger(port) || port < 1 || port > 65535) throw new Error("Enter a port between 1 and 65535.");

  const country = normalizeCountry(input?.country);
  const timezone = normalizeTimezone(input?.timezone);
  const locale = normalizeLocale(input?.locale);
  const latitude = normalizeCoordinate(input?.latitude, -90, 90, "latitude");
  const longitude = normalizeCoordinate(input?.longitude, -180, 180, "longitude");
  const userAgent = String(input?.userAgent ?? "").trim().slice(0, 600);
  const rotationUrl = normalizeOptionalUrl(input?.rotationUrl);
  const providerId = String(input?.providerId ?? "").trim().slice(0, 80);
  const providerSlug = String(input?.providerSlug ?? "").trim().slice(0, 80);
  const notes = String(input?.notes ?? "").trim().slice(0, 2000);

  return {
    name,
    kind: /** @type {ProxyProfileKind} */ (kind),
    protocol: /** @type {ProxyProtocol} */ (protocol),
    host,
    port,
    username: String(input?.username ?? "").trim().slice(0, 300),
    country,
    timezone,
    locale,
    latitude,
    longitude,
    userAgent,
    rotationUrl,
    providerId,
    providerSlug,
    notes,
    applyToApp: input?.applyToApp !== false,
    alignIdentity: input?.alignIdentity !== false,
  };
}

/**
 * Accepts "host", "host:port", "[2001:db8::1]:8080", or a full proxy URL.
 * @param {string} value
 */
export function splitProxyAddress(value) {
  const raw = value.trim();
  if (!raw) return { host: "", port: null };
  if (/^[a-z][a-z0-9+.-]*:\/\//i.test(raw)) {
    try {
      const url = new URL(raw);
      return {
        host: url.hostname,
        port: url.port ? Number(url.port) : null,
        username: decodeURIComponent(url.username || ""),
        password: decodeURIComponent(url.password || ""),
        protocol: url.protocol.replace(":", ""),
      };
    } catch {
      throw new Error("The proxy address is not valid.");
    }
  }
  const bracketed = raw.match(/^\[([^\]]+)\](?::(\d+))?$/);
  if (bracketed) return { host: bracketed[1], port: bracketed[2] ? Number(bracketed[2]) : null };
  const lastColon = raw.lastIndexOf(":");
  if (lastColon > 0 && raw.indexOf(":") === lastColon) {
    return { host: raw.slice(0, lastColon), port: Number(raw.slice(lastColon + 1)) || null };
  }
  return { host: raw.replace(/^\[|\]$/g, ""), port: null };
}

/**
 * @param {string} host
 */
export function normalizeHost(host) {
  return host.trim().replace(/^\[|\]$/g, "").replace(/\/+$/, "").slice(0, 255);
}

/**
 * @param {string | undefined} value
 */
function normalizeSecret(value) {
  return String(value ?? "").slice(0, 1000);
}

/**
 * @param {unknown} value
 */
function normalizeCountry(value) {
  const country = String(value ?? "").trim().toLowerCase();
  if (!country) return "";
  if (country === "global") return "global";
  if (!/^[a-z]{2,3}$/.test(country)) throw new Error("Use a two-letter country code, or global.");
  return country;
}

/**
 * @param {unknown} value
 */
function normalizeTimezone(value) {
  const timezone = String(value ?? "").trim();
  if (!timezone) return "";
  if (timezone.length > 80 || !/^[A-Za-z_+\-/]+$/.test(timezone)) {
    throw new Error("Use an IANA timezone such as Europe/London.");
  }
  try {
    Intl.DateTimeFormat("en", { timeZone: timezone });
  } catch {
    throw new Error("That timezone is not recognized.");
  }
  return timezone;
}

/**
 * @param {unknown} value
 */
function normalizeLocale(value) {
  const locale = String(value ?? "").trim();
  if (!locale) return "";
  if (!/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,8})?$/.test(locale)) {
    throw new Error("Use a locale such as en-US or ar.");
  }
  return locale.replace("_", "-");
}

/**
 * @param {unknown} value
 * @param {number} min
 * @param {number} max
 * @param {string} label
 */
function normalizeCoordinate(value, min, max, label) {
  if (value === null || value === undefined || value === "") return null;
  const number = Number(value);
  if (!Number.isFinite(number) || number < min || number > max) {
    throw new Error(`The ${label} is outside its valid range.`);
  }
  return Math.round(number * 1e6) / 1e6;
}

/**
 * @param {unknown} value
 */
function normalizeOptionalUrl(value) {
  const url = String(value ?? "").trim();
  if (!url) return "";
  try {
    const parsed = new URL(url);
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") throw new Error();
    return parsed.toString().slice(0, 2048);
  } catch {
    throw new Error("The rotation URL must start with http:// or https://.");
  }
}
