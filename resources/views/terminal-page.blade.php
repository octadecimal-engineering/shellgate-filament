<x-filament-panels::page>
    <div
        x-data="shellGateTerminal({
            gatewayUrl: @js($gatewayUrl),
            tokenEndpoint: @js($tokenEndpoint),
            config: @js($terminalConfig),
            csrfToken: @js(csrf_token()),
            height: @js($height),
        })"
        x-init="init()"
        class="shell-gate-container"
    >
        {{-- Terminal window frame --}}
        <div class="shell-gate-window">

            {{-- macOS-style title bar --}}
            <div class="shell-gate-titlebar">
                <div class="shell-gate-titlebar-left">
                    <span class="shell-gate-dot shell-gate-dot-red"></span>
                    <span class="shell-gate-dot shell-gate-dot-yellow"></span>
                    <span class="shell-gate-dot shell-gate-dot-green"></span>
                </div>
                <div class="shell-gate-titlebar-center">
                    <svg class="shell-gate-titlebar-icon" x-bind:class="{ 'shell-gate-gear-spin': gearSpinning }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 60" fill="none"><path d="M 48 30 L 48 30 L 42.93431345515801 35.35756805311126 L 42.72792206135786 42.72792206135786 L 42.72792206135786 42.72792206135786 L 35.35756805311126 42.93431345515801 L 30 48 L 30 48 L 24.642431946888742 42.93431345515801 L 17.272077938642145 42.72792206135786 L 17.272077938642145 42.72792206135786 L 17.065686544841988 35.35756805311126 L 12 30.000000000000004 L 12 30.000000000000004 L 17.065686544841984 24.642431946888745 L 17.27207793864214 17.272077938642145 L 17.27207793864214 17.272077938642145 L 24.642431946888735 17.065686544841988 L 29.999999999999996 12 L 29.999999999999996 12 L 35.35756805311126 17.065686544841988 L 42.72792206135785 17.27207793864214 L 42.72792206135785 17.27207793864214 L 42.93431345515801 24.642431946888735 L 48 29.999999999999996 Z" stroke="currentColor" stroke-width="2.8" fill="none" stroke-linejoin="miter" stroke-linecap="square"/><circle cx="30" cy="30" r="9" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="30" cy="30" r="4.5" fill="none" stroke="currentColor" stroke-width="1.3" opacity="0.5"/></svg>
                    <span class="shell-gate-titlebar-text">ShellGate Terminal</span>
                </div>
                <div class="shell-gate-titlebar-right">
                    <span class="shell-gate-titlebar-status" x-bind:class="{
                        'shell-gate-titlebar-status--connected': connected,
                        'shell-gate-titlebar-status--disconnected': !connected && !connecting,
                        'shell-gate-titlebar-status--connecting': connecting,
                    }">
                        <span class="shell-gate-titlebar-status-dot"></span>
                        <span x-text="statusText" class="shell-gate-titlebar-status-text"></span>
                    </span>
                </div>
            </div>

            {{-- Terminal body --}}
            <div class="shell-gate-body">
                <div
                    x-ref="terminal"
                    class="shell-gate-terminal"
                    x-bind:style="{ height: (terminalHeight - terminalBottomMargin) + 'px' }"
                ></div>

                {{-- Connecting overlay --}}
                <div
                    x-show="connecting && !connected"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="shell-gate-overlay"
                >
                    <div class="shell-gate-overlay-content">
                        <svg class="shell-gate-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke-linecap="round"/>
                        </svg>
                        <span class="shell-gate-overlay-text">Establishing secure connection<span class="shell-gate-overlay-dots">...</span></span>
                    </div>
                </div>
            </div>

            {{-- Error banner (inside frame) --}}
            <div
                x-show="error"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="shell-gate-error"
            >
                <svg class="shell-gate-error-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <span x-text="error" class="shell-gate-error-text"></span>
            </div>

            {{-- Integrated status bar (bottom) --}}
            <div class="shell-gate-statusbar">
                <div class="shell-gate-statusbar-left">
                    <span class="shell-gate-statusbar-connection" x-bind:class="{
                        'shell-gate-statusbar-connection--connected': connected,
                        'shell-gate-statusbar-connection--disconnected': !connected && !connecting,
                        'shell-gate-statusbar-connection--connecting': connecting,
                    }">
                        <span class="shell-gate-statusbar-dot"></span>
                        <span x-text="statusText"></span>
                    </span>
                    <span x-show="sessionId" class="shell-gate-statusbar-session">
                        <span class="shell-gate-statusbar-session-label">SESSION</span>
                        <span x-text="sessionId?.substring(0, 8)" class="shell-gate-statusbar-session-id"></span>
                    </span>
                </div>
                <div class="shell-gate-statusbar-right">
                    {{-- Reconnect button --}}
                    <button
                        x-show="!connected && !connecting"
                        x-on:click="connect()"
                        x-transition
                        type="button"
                        class="shell-gate-btn shell-gate-btn-reconnect"
                    >
                        <svg class="shell-gate-btn-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1.5 8a6.5 6.5 0 0 1 11.25-4.5M14.5 8a6.5 6.5 0 0 1-11.25 4.5"/>
                            <path d="M13.5 2.5v3h-3M2.5 13.5v-3h3"/>
                        </svg>
                        Reconnect
                    </button>

                    {{-- Disconnect button --}}
                    <button
                        x-show="connected"
                        x-on:click="disconnect()"
                        x-transition
                        type="button"
                        class="shell-gate-btn shell-gate-btn-disconnect"
                    >
                        <svg class="shell-gate-btn-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="10" height="10" rx="1"/>
                        </svg>
                        Disconnect
                    </button>
                </div>
            </div>

            {{-- Resize handle --}}
            <div
                class="shell-gate-resize-handle"
                x-on:mousedown.prevent="startResize($event)"
            >
                <svg class="shell-gate-resize-icon" viewBox="0 0 16 6" fill="currentColor">
                    <circle cx="4" cy="1.5" r="1"/>
                    <circle cx="8" cy="1.5" r="1"/>
                    <circle cx="12" cy="1.5" r="1"/>
                    <circle cx="4" cy="4.5" r="1"/>
                    <circle cx="8" cy="4.5" r="1"/>
                    <circle cx="12" cy="4.5" r="1"/>
                </svg>
            </div>

        </div>
    </div>

    {{-- Alpine.js component script --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('shellGateTerminal', (options) => ({
                terminal: null,
                fitAddon: null,
                socket: null,
                connected: false,
                connecting: false,
                sessionId: null,
                error: null,
                gearSpinning: false,
                terminalHeight: 500,
                terminalBottomMargin: 56,
                resizing: false,
                resizeStartY: 0,
                resizeStartH: 0,

                get statusText() {
                    if (this.connecting) return 'Connecting...';
                    if (this.connected) return 'Connected';
                    return 'Disconnected';
                },

                startResize(e) {
                    this.resizing = true;
                    this.resizeStartY = e.clientY;
                    this.resizeStartH = this.terminalHeight;
                    document.body.style.cursor = 'ns-resize';
                    document.body.style.userSelect = 'none';

                    const onMouseMove = (ev) => {
                        const delta = ev.clientY - this.resizeStartY;
                        this.terminalHeight = Math.max(200, this.resizeStartH + delta);
                        this.fit();
                    };
                    const onMouseUp = () => {
                        this.resizing = false;
                        document.body.style.cursor = '';
                        document.body.style.userSelect = '';
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                    };
                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                },

                async init() {
                    // Load xterm.js dynamically if not already loaded
                    if (typeof Terminal === 'undefined') {
                        await this.loadXterm();
                    }

                    this.initTerminal();
                    this.connect();

                    // Spin gear icon on load
                    this.gearSpinning = true;
                    setTimeout(() => { this.gearSpinning = false; }, 3000);

                    // Handle window resize
                    window.addEventListener('resize', () => this.fit());
                },

                async loadXterm() {
                    // Load xterm.js CSS
                    if (!document.querySelector('link[href*="xterm.css"]')) {
                        const link = document.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = 'https://cdn.jsdelivr.net/npm/xterm@5/css/xterm.css';
                        document.head.appendChild(link);
                    }

                    // Load xterm.js
                    await this.loadScript('https://cdn.jsdelivr.net/npm/xterm@5/lib/xterm.min.js');

                    // Load fit addon
                    await this.loadScript('https://cdn.jsdelivr.net/npm/@xterm/addon-fit@0.10/lib/addon-fit.min.js');
                },

                loadScript(src) {
                    return new Promise((resolve, reject) => {
                        if (document.querySelector(`script[src="${src}"]`)) {
                            resolve();
                            return;
                        }
                        const script = document.createElement('script');
                        script.src = src;
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                },

                initTerminal() {
                    const theme = options.config.theme === 'dark' ? {
                        background: '#1e1e2e',
                        foreground: '#cdd6f4',
                        cursor: '#f5e0dc',
                        selection: '#45475a',
                        black: '#45475a',
                        red: '#f38ba8',
                        green: '#a6e3a1',
                        yellow: '#f9e2af',
                        blue: '#89b4fa',
                        magenta: '#f5c2e7',
                        cyan: '#94e2d5',
                        white: '#bac2de',
                    } : {
                        background: '#ffffff',
                        foreground: '#1e1e2e',
                        cursor: '#1e1e2e',
                        selection: '#e0e0e0',
                    };

                    // Apply custom colors if provided
                    if (options.config.colors && Object.keys(options.config.colors).length > 0) {
                        Object.assign(theme, options.config.colors);
                    }

                    this.terminal = new Terminal({
                        cursorBlink: true,
                        fontSize: options.config.fontSize || 14,
                        fontFamily: options.config.fontFamily || 'JetBrains Mono, Menlo, Monaco, monospace',
                        theme: theme,
                        cols: options.config.cols || 120,
                        rows: options.config.rows || 30,
                        allowProposedApi: true,
                    });

                    this.fitAddon = new FitAddon.FitAddon();
                    this.terminal.loadAddon(this.fitAddon);

                    this.terminal.open(this.$refs.terminal);
                    this.fit();

                    // Handle terminal input
                    this.terminal.onData((data) => {
                        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
                            this.socket.send(data);
                        }
                    });
                },

                fit() {
                    if (this.fitAddon) {
                        this.fitAddon.fit();
                        this.sendResize();
                    }
                },

                sendResize() {
                    if (this.socket && this.socket.readyState === WebSocket.OPEN && this.terminal) {
                        this.socket.send(JSON.stringify({
                            type: 'resize',
                            cols: this.terminal.cols,
                            rows: this.terminal.rows,
                        }));
                    }
                },

                async connect() {
                    this.error = null;
                    this.connecting = true;

                    try {
                        // Get token from API
                        const response = await fetch(options.tokenEndpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': options.csrfToken,
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            const data = await response.json().catch(() => ({}));
                            throw new Error(data.error || `HTTP ${response.status}`);
                        }

                        const data = await response.json();
                        this.sessionId = data.session_id;

                        // Connect to WebSocket
                        const wsUrl = `${data.gateway_url}?token=${encodeURIComponent(data.token)}`;
                        this.socket = new WebSocket(wsUrl);

                        this.socket.onopen = () => {
                            this.sendResize();
                            // Set prompt and clear screen before revealing
                            this.socket.send("PROMPT='%~ \u276F '\nclear\n");
                            setTimeout(() => {
                                this.terminal.clear();
                                this.connected = true;
                                this.connecting = false;
                                this.terminal.focus();
                                this.sendResize();
                            }, 400);
                        };

                        this.socket.onmessage = (event) => {
                            this.terminal.write(event.data);
                        };

                        this.socket.onclose = (event) => {
                            this.connected = false;
                            this.connecting = false;

                            if (event.code !== 1000) {
                                const reasons = {
                                    4001: 'Invalid token',
                                    4002: 'Token expired',
                                    4003: 'IP/User-Agent mismatch',
                                    4004: 'Origin not allowed',
                                    4005: 'Max sessions reached',
                                };
                                this.error = reasons[event.code] || `Connection closed (code: ${event.code})`;
                                this.terminal.write(`\r\n\x1b[31m[Connection closed: ${this.error}]\x1b[0m\r\n`);
                            }
                        };

                        this.socket.onerror = () => {
                            this.error = 'WebSocket connection error';
                            this.connecting = false;
                        };

                    } catch (err) {
                        this.error = err.message || 'Failed to connect';
                        this.connecting = false;
                        this.terminal.write(`\r\n\x1b[31m[Error: ${this.error}]\x1b[0m\r\n`);
                    }
                },

                disconnect() {
                    if (this.socket) {
                        this.socket.close(1000, 'User disconnect');
                        this.socket = null;
                    }
                    this.connected = false;
                    this.sessionId = null;
                    this.terminal.write('\r\n\x1b[33m[Disconnected]\x1b[0m\r\n');
                },

                destroy() {
                    this.disconnect();
                    if (this.terminal) {
                        this.terminal.dispose();
                    }
                    window.removeEventListener('resize', this.fit);
                }
            }));
        });
    </script>

    {{-- Styles --}}
    <style>
        /* ===== Hide Filament page header ===== */
        .shell-gate-container {
            padding-top: 1.5rem;
        }

        .fi-header {
            display: none !important;
        }

        .fi-page-header-main-ctn {
            padding-top: 0 !important;
            gap: 0 !important;
        }

        /* ===== Container ===== */
        .shell-gate-container {
            width: 100%;
        }

        /* ===== Window Frame ===== */
        .shell-gate-window {
            border-radius: 12px;
            overflow: hidden;
            box-shadow:
                0 25px 60px -10px rgba(0, 0, 0, 0.55),
                0 12px 25px -5px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
        }

        /* ===== Title Bar ===== */
        .shell-gate-titlebar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: linear-gradient(180deg, #2a2a3c 0%, #232334 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            user-select: none;
            -webkit-user-select: none;
            position: relative;
        }

        .shell-gate-titlebar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 80px;
        }

        .shell-gate-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            transition: opacity 0.15s ease;
        }

        .shell-gate-dot:hover {
            opacity: 0.8;
        }

        .shell-gate-dot-red {
            background: #ff5f57;
            box-shadow: 0 0 0 0.5px rgba(0, 0, 0, 0.12), inset 0 -1px 0 rgba(0, 0, 0, 0.12);
        }

        .shell-gate-dot-yellow {
            background: #febc2e;
            box-shadow: 0 0 0 0.5px rgba(0, 0, 0, 0.12), inset 0 -1px 0 rgba(0, 0, 0, 0.12);
        }

        .shell-gate-dot-green {
            background: #28c840;
            box-shadow: 0 0 0 0.5px rgba(0, 0, 0, 0.12), inset 0 -1px 0 rgba(0, 0, 0, 0.12);
        }

        .shell-gate-titlebar-center {
            display: flex;
            align-items: center;
            gap: 1px;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .shell-gate-titlebar-icon {
            width: 36px;
            height: 36px;
            opacity: 0.45;
            color: #fff;
        }

        .shell-gate-gear-spin {
            animation: sg-gear-decel 3s cubic-bezier(0.12, 0.6, 0.3, 1) forwards;
        }

        .shell-gate-titlebar-text {
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 0.01em;
        }

        .shell-gate-titlebar-right {
            display: flex;
            align-items: center;
            min-width: 80px;
            justify-content: flex-end;
        }

        .shell-gate-titlebar-status {
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .shell-gate-titlebar-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .shell-gate-titlebar-status--connected .shell-gate-titlebar-status-dot {
            background: #28c840;
            box-shadow: 0 0 6px rgba(40, 200, 64, 0.4);
        }

        .shell-gate-titlebar-status--disconnected .shell-gate-titlebar-status-dot {
            background: #ff5f57;
            box-shadow: 0 0 6px rgba(255, 95, 87, 0.3);
        }

        .shell-gate-titlebar-status--connecting .shell-gate-titlebar-status-dot {
            background: #febc2e;
            box-shadow: 0 0 6px rgba(254, 188, 46, 0.3);
            animation: sg-pulse 1.5s ease-in-out infinite;
        }

        .shell-gate-titlebar-status-text {
            font-size: 11px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ===== Terminal Body ===== */
        .shell-gate-body {
            position: relative;
            background: #1e1e2e;
        }

        .shell-gate-terminal {
            padding: 8px;
        }

        .shell-gate-terminal .xterm {
            padding: 0;
        }

        .shell-gate-terminal .xterm-viewport {
            overflow-y: auto !important;
        }

        /* Subtle inner glow on the terminal area */
        .shell-gate-body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg,
                transparent 0%,
                rgba(137, 180, 250, 0.08) 50%,
                transparent 100%
            );
            z-index: 1;
            pointer-events: none;
        }

        /* ===== Connecting Overlay ===== */
        .shell-gate-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(30, 30, 46, 0.92);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 10;
        }

        .shell-gate-overlay-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .shell-gate-spinner {
            width: 32px;
            height: 32px;
            color: rgba(137, 180, 250, 0.6);
            animation: sg-spin 1.2s linear infinite;
        }

        .shell-gate-overlay-text {
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 0.02em;
        }

        .shell-gate-overlay-dots {
            animation: sg-dots 1.4s steps(4, end) infinite;
            display: inline-block;
            width: 1.2em;
            text-align: left;
            overflow: hidden;
            vertical-align: bottom;
        }

        /* ===== Error Banner ===== */
        .shell-gate-error {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: rgba(243, 139, 168, 0.08);
            border-top: 1px solid rgba(243, 139, 168, 0.15);
        }

        .shell-gate-error-icon {
            width: 16px;
            height: 16px;
            color: #f38ba8;
            flex-shrink: 0;
        }

        .shell-gate-error-text {
            font-size: 12px;
            color: #f38ba8;
            font-weight: 500;
        }

        /* ===== Status Bar ===== */
        .shell-gate-statusbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            background: rgba(30, 30, 46, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .shell-gate-statusbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .shell-gate-statusbar-connection {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
        }

        .shell-gate-statusbar-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .shell-gate-statusbar-connection--connected .shell-gate-statusbar-dot {
            background: #28c840;
            box-shadow: 0 0 4px rgba(40, 200, 64, 0.4);
        }

        .shell-gate-statusbar-connection--disconnected .shell-gate-statusbar-dot {
            background: #ff5f57;
        }

        .shell-gate-statusbar-connection--connecting .shell-gate-statusbar-dot {
            background: #febc2e;
            animation: sg-pulse 1.5s ease-in-out infinite;
        }

        .shell-gate-statusbar-session {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .shell-gate-statusbar-session-label {
            font-size: 9px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .shell-gate-statusbar-session-id {
            font-size: 11px;
            font-family: 'JetBrains Mono', 'Menlo', 'Monaco', monospace;
            color: rgba(255, 255, 255, 0.3);
        }

        .shell-gate-statusbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== Action Buttons ===== */
        .shell-gate-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            line-height: 1.5;
        }

        .shell-gate-btn-icon {
            width: 12px;
            height: 12px;
        }

        .shell-gate-btn-reconnect {
            background: rgba(137, 180, 250, 0.1);
            color: #89b4fa;
            border: 1px solid rgba(137, 180, 250, 0.15);
        }

        .shell-gate-btn-reconnect:hover {
            background: rgba(137, 180, 250, 0.2);
            border-color: rgba(137, 180, 250, 0.3);
        }

        .shell-gate-btn-disconnect {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .shell-gate-btn-disconnect:hover {
            background: rgba(243, 139, 168, 0.1);
            color: #f38ba8;
            border-color: rgba(243, 139, 168, 0.2);
        }

        /* ===== Resize Handle ===== */
        .shell-gate-resize-handle {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 10px;
            background: #1a1a2e;
            cursor: ns-resize;
            opacity: 0;
            transition: opacity 0.2s ease;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .shell-gate-window:hover .shell-gate-resize-handle {
            opacity: 1;
        }

        .shell-gate-resize-icon {
            width: 16px;
            height: 6px;
            color: rgba(255, 255, 255, 0.2);
            pointer-events: none;
        }

        .shell-gate-resize-handle:hover .shell-gate-resize-icon {
            color: rgba(255, 255, 255, 0.4);
        }

        /* ===== Animations ===== */
        @keyframes sg-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        @keyframes sg-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes sg-gear-decel {
            0%   { transform: rotate(0deg); }
            100% { transform: rotate(1080deg); }
        }

        @keyframes sg-dots {
            0% { content: ''; width: 0; }
            25% { content: '.'; width: 0.4em; }
            50% { content: '..'; width: 0.8em; }
            75% { content: '...'; width: 1.2em; }
            100% { content: ''; width: 0; }
        }
    </style>
</x-filament-panels::page>
