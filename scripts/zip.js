import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { spawnSync } from 'child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configuration
const PLUGIN_ROOT = path.join(__dirname, '..');
const DIST_PRODUCTION_DIR = path.join(PLUGIN_ROOT, 'dist-production');
const TEMP_PLUGIN_DIR = path.join(DIST_PRODUCTION_DIR, 'pickory-product-filter');
const ZIPPED_FILE = path.join(DIST_PRODUCTION_DIR, 'pickory-product-filter.zip');

/**
 * Get human-readable file size
 */
function formatBytes(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Main zip function
 */
function zipProduction() {
  console.log('\n🤐 Pickory Product Filter ZIP Creator 🤐\n');

  // Validate temp folder exists
  if (!fs.existsSync(TEMP_PLUGIN_DIR)) {
    console.error('❌ Error: dist-production/pickory-product-filter/ folder not found!');
    console.error('   Run "npm run export:production" first.');
    process.exit(1);
  }

  // Create zip
  console.log('Creating ZIP file...');
  const isWindows = process.platform === 'win32';
  try {
    if (isWindows) {
      spawnSync('powershell.exe', [
        '-NoProfile',
        '-Command',
        `Compress-Archive -Path '${TEMP_PLUGIN_DIR}\\*' -DestinationPath '${ZIPPED_FILE}' -Force`
      ], { stdio: 'inherit' });
    } else {
      // Linux/macOS: use zip command
      spawnSync('zip', ['-r', ZIPPED_FILE, 'pickory-product-filter'], {
        cwd: DIST_PRODUCTION_DIR,
        stdio: 'inherit'
      });
    }
    console.log('✅ ZIP file created!\n');
  } catch (err) {
    console.error('❌ Failed to create ZIP file!');
    process.exit(1);
  }

  // Summary
  const stats = fs.statSync(ZIPPED_FILE);
  console.log('✅ ZIP completed successfully!');
  console.log('------------------------------------');
  console.log(`📏 ZIP size: ${formatBytes(stats.size)}`);
  console.log(`📂 Output: ${ZIPPED_FILE}`);
  console.log('------------------------------------\n');
}

zipProduction();
