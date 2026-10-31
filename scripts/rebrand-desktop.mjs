#!/usr/bin/env node

/**
 * Desktop App Rebranding Script
 * Replaces all 'opencode' references with 'idexal Os' in source files
 * Only modifies source code, NOT compiled/dist files
 */

import { glob } from 'glob';
import { readFile, writeFile, readdir, stat } from 'fs/promises';
import { join } from 'path';

const REPLACEMENTS = [
  // User-facing strings
  [/OpenCode|open code/i, 'idexal Os'],
  [/opencod[ae]/gi, 'idexal-os'],
  
  // UI titles and labels
  ['OpenCode Desktop', 'idexal Os Desktop'],
  ['OpenCode Workspace', 'idexal Os Workspace'],
  ['OpenCode Config', 'idexal Os Config'],
  
  // Error messages
  ['opencode session', 'idexal-os session'],
  ['opencode error', 'idexal os error'],
  
  // Plugin names (directories only in build)
  ['opencode-plugin-', 'idexal-os-'],
  
  // Internal constants (if user-facing)
  ['/OPENCODE/i', '/IDEXAL_OS/'],
];

async function processFile(filePath) {
  try {
    const content = await readFile(filePath, 'utf8');
    let modifiedContent = content;
    
    for (const [pattern, replacement] of REPLACEMENTS) {
      if (typeof pattern === 'string') {
        modifiedContent = modifiedContent.split(pattern).join(replacement);
      } else {
        modifiedContent = modifiedContent.replace(pattern, replacement);
      }
    }
    
    if (modifiedContent !== content) {
      console.log(`✓ Modified: ${filePath}`);
      await writeFile(filePath, modifiedContent, 'utf8');
      return true;
    } else {
      console.log(`⊘ No changes: ${filePath}`);
      return false;
    }
  } catch (error) {
    console.error(`✗ Error processing ${filePath}:`, error.message);
    return null;
  }
}

async function main() {
  console.log('🔄 Starting idexal Os rebranding...\n');
  
  // Find all source files (excluding dist, node_modules, build)
  const patterns = [
    'apps/app/src/**/*.ts',
    'apps/app/src/**/*.tsx', 
    'apps/app/src/**/*.js',
    'apps/desktop/src/**/*.ts',
    'apps/desktop/src/**/*.tsx',
    'packages/**/src/**/*.ts',
  ];
  
  let totalFiles = 0;
  let modifiedCount = 0;
  let errorCount = 0;
  
  for (const pattern of patterns) {
    try {
      const files = await glob(pattern, { 
        ignore: [
          '**/node_modules/**',
          '**/dist/**',
          '**/build/**',
          '**/.*/**',
        ]
      });
      
      totalFiles += files.length;
      
      for (const filePath of files) {
        const result = await processFile(filePath);
        if (result === true) modifiedCount++;
        if (result === null) errorCount++;
      }
    } catch (error) {
      console.warn(`⚠️ Pattern skipped: ${pattern}`);
    }
  }
  
  console.log('\n✅ Rebranding complete!');
  console.log(`Total files processed: ${totalFiles}`);
  console.log(`Modified: ${modifiedCount}`);
  console.log(`Errors: ${errorCount}`);
  
  if (modifiedCount > 0) {
    console.log('\n⚠️ IMPORTANT: You must rebuild the desktop app now!');
    console.log('Run: cd apps/desktop && pnpm run package:electron\n');
  }
}

main().catch(console.error);