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
        {{-- Terminal container --}}
        <div
            x-ref="terminal"
            class="shell-gate-terminal rounded-lg overflow-hidden"
            x-bind:style="{ height: options.height }"
        ></div>

        {{-- Status bar --}}
        <div class="shell-gate-status mt-2 flex items-center justify-between text-sm">
            <div class="flex items-center gap-4">
                {{-- Connection status --}}
                <span class="flex items-center gap-2">
                    <span
                        x-show="connected"
                        class="w-2 h-2 rounded-full bg-green-500"
                    ></span>
                    <span
                        x-show="!connected && !connecting"
                        class="w-2 h-2 rounded-full bg-red-500"
                    ></span>
                    <span
                        x-show="connecting"
                        class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"
                    ></span>
                    <span x-text="statusText" class="text-gray-500 dark:text-gray-400"></span>
                </span>

                {{-- Session ID --}}
                <span x-show="sessionId" class="text-gray-400 dark:text-gray-500 text-xs">
                    Session: <span x-text="sessionId?.substring(0, 8)"></span>...
                </span>
            </div>

            <div class="flex items-center gap-2">
                {{-- Reconnect button --}}
                <button
                    x-show="!connected && !connecting"
                    x-on:click="connect()"
                    type="button"
                    class="text-primary-500 hover:text-primary-600 text-xs"
                >
                    Reconnect
                </button>

                {{-- Disconnect button --}}
                <button
                    x-show="connected"
                    x-on:click="disconnect()"
                    type="button"
                    class="text-gray-400 hover:text-red-500 text-xs"
                >
                    Disconnect
                </button>
            </div>
        </div>

        {{-- Error message --}}
        <div
            x-show="error"
            x-transition
            class="mt-2 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm"
        >
            <span x-text="error"></span>
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

                get statusText() {
                    if (this.connecting) return 'Connecting...';
                    if (this.connected) return 'Connected';
                    return 'Disconnected';
                },

                async init() {
                    // Load xterm.js dynamically if not already loaded
                    if (typeof Terminal === 'undefined') {
                        await this.loadXterm();
                    }

                    this.initTerminal();
                    this.connect();

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
                            this.connected = true;
                            this.connecting = false;
                            this.terminal.focus();
                            this.sendResize();
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

    {{-- Inline styles for terminal container --}}
    <style>
        .shell-gate-container {
            width: 100%;
        }

        .shell-gate-terminal {
            background: #1e1e2e;
            padding: 8px;
        }

        .shell-gate-terminal .xterm {
            padding: 0;
        }

        .shell-gate-terminal .xterm-viewport {
            overflow-y: auto !important;
        }

        /* Dark mode adjustments */
        .dark .shell-gate-terminal {
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Light mode adjustments */
        :not(.dark) .shell-gate-terminal {
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
    </style>
</x-filament-panels::page>
