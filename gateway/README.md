# Shell Gate Gateway

Node.js WebSocket server that spawns PTY sessions for the Shell Gate Filament terminal.

## Setup

```bash
npm install
npm start
```

Copy `.env.example` to `.env` and set `JWT_SECRET` to match your Laravel `APP_KEY` (or `SHELL_GATE_JWT_SECRET`).

## macOS: "Connection closed (code 4006)" / posix_spawnp failed

The `node-pty` dependency includes a native `spawn-helper` binary. On macOS it often is not executable after install. This package runs a **postinstall** script (`scripts/fix-spawn-helper.js`) that sets `chmod +x` on `spawn-helper` in all `node-pty` prebuilds.

- Run `npm install` (do not use `npm ci --ignore-scripts`) so postinstall runs.
- If you deploy without running install, apply the fix manually:  
  `chmod +x node_modules/node-pty/prebuilds/darwin-arm64/spawn-helper` (or `darwin-x64` on Intel).

See the main package [INSTALLATION.md](../INSTALLATION.md) → Troubleshooting for details.
