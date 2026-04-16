#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Get version from command line argument
const version = process.argv[2];

if (!version) {
  console.error('Usage: npm run release v1.0.0');
  process.exit(1);
}

// Remove 'v' prefix if present
const cleanVersion = version.replace(/^v/, '');

const rootDir = path.join(__dirname, '..');
const packageJsonPath = path.join(rootDir, 'package.json');
const pluginFilePath = path.join(rootDir, 'cm-instagram-feed.php');

try {
  console.log(`\n📦 Releasing version ${cleanVersion}...\n`);

  // Update package.json
  console.log('✓ Updating package.json');
  const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
  packageJson.version = cleanVersion;
  fs.writeFileSync(packageJsonPath, JSON.stringify(packageJson, null, 2) + '\n');

  // Update plugin header
  console.log('✓ Updating cm-instagram-feed.php header');
  let pluginContent = fs.readFileSync(pluginFilePath, 'utf8');
  pluginContent = pluginContent.replace(
    /\* Version: [0-9.]+/,
    `* Version: ${cleanVersion}`
  );
  fs.writeFileSync(pluginFilePath, pluginContent);

  console.log(`\n✅ Version updated to ${cleanVersion}\n`);
  console.log('Next steps:');
  console.log(`  1. git add package.json cm-instagram-feed.php`);
  console.log(`  2. git commit -m "Release v${cleanVersion}"`);
  console.log(`  3. git tag -a v${cleanVersion} -m "v${cleanVersion}"`);
  console.log(`  4. git push origin main --tags\n`);

} catch (error) {
  console.error('❌ Release failed:', error.message);
  process.exit(1);
}
