/**
 * Ensures node-pty spawn-helper is executable on macOS (fixes posix_spawnp failed).
 * Run automatically after npm install via postinstall.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const nodePtyRoot = path.join(__dirname, '..', 'node_modules', 'node-pty');
const prebuildsDir = path.join(nodePtyRoot, 'prebuilds');

try {
  if (!fs.existsSync(prebuildsDir)) process.exit(0);
  for (const arch of fs.readdirSync(prebuildsDir)) {
    const archPath = path.join(prebuildsDir, arch);
    if (!fs.statSync(archPath).isDirectory()) continue;
    const helper = path.join(archPath, 'spawn-helper');
    if (fs.existsSync(helper)) {
      fs.chmodSync(helper, 0o755);
      console.log('[shell-gate-gateway] chmod +x', helper);
    }
  }
} catch (e) {
  // ignore (e.g. no node-pty yet, wrong path)
}
