<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OutreachAI — AI-Powered Personal Outreach CRM</title>
    <meta name="description" content="Track scholarships, jobs, and research grants. Let GPT draft your emails. Schedule smart follow-ups. Never miss an opportunity again.">
    <meta property="og:title" content="OutreachAI — AI-Powered Personal Outreach CRM">
    <meta property="og:description" content="Your AI-powered outreach pipeline. GPT Actions + MCP integration built-in.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }

        /* ── Gradient text ── */
        .gradient-text {
            background: linear-gradient(135deg, #a78bfa 0%, #6366f1 45%, #38bdf8 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .gradient-text-warm {
            background: linear-gradient(135deg, #fb923c 0%, #f472b6 50%, #a78bfa 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        /* ── Glass morphism ── */
        .glass {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .glass-hover:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(255,255,255,0.14);
        }

        /* ── Grid background ── */
        .grid-bg {
            background-image:
                linear-gradient(rgba(99,102,241,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99,102,241,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* ── Mesh blobs ── */
        .blob { border-radius: 50%; filter: blur(80px); position: absolute; pointer-events: none; }
        .blob-1 { background: radial-gradient(circle, rgba(99,102,241,0.35), transparent 70%); }
        .blob-2 { background: radial-gradient(circle, rgba(168,85,247,0.25), transparent 70%); }
        .blob-3 { background: radial-gradient(circle, rgba(6,182,212,0.2), transparent 70%); }
        .blob-4 { background: radial-gradient(circle, rgba(244,114,182,0.15), transparent 70%); }

        /* ── Animations ── */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33%       { transform: translateY(-14px) rotate(1deg); }
            66%       { transform: translateY(-7px) rotate(-1deg); }
        }
        @keyframes float2 {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(-18px); }
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50%       { opacity: 1; transform: scale(1.3); }
        }
        @keyframes shimmer {
            0%   { background-position: -400px 0; }
            100% { background-position: 400px 0; }
        }
        @keyframes slide-up {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes glow-pulse {
            0%, 100% { box-shadow: 0 0 30px rgba(99,102,241,0.3); }
            50%       { box-shadow: 0 0 60px rgba(99,102,241,0.6); }
        }
        @keyframes typing {
            0%  { width: 0; }
            40% { width: 100%; }
            60% { width: 100%; }
            100%{ width: 0; }
        }

        .float-1 { animation: float  6s ease-in-out infinite; }
        .float-2 { animation: float2 8s ease-in-out infinite 1s; }
        .float-3 { animation: float  7s ease-in-out infinite 2s; }
        .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }
        .slide-up { animation: slide-up 0.7s ease-out both; }
        .glow-pulse { animation: glow-pulse 3s ease-in-out infinite; }

        /* ── Feature cards ── */
        .feature-card {
            transition: transform 0.3s cubic-bezier(.34,1.56,.64,1), box-shadow 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-6px);
        }
        .feature-card:hover .icon-wrap {
            transform: scale(1.1);
        }
        .icon-wrap { transition: transform 0.3s ease; }

        /* ── Roadmap cards ── */
        .roadmap-card {
            border-left: 3px solid;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .roadmap-card:hover { transform: translateX(6px); background: rgba(255,255,255,0.07); }

        /* ── Nav scroll effect ── */
        .nav-scrolled {
            background: rgba(7,7,17,0.9) !important;
            border-bottom-color: rgba(255,255,255,0.1) !important;
        }

        /* ── Terminal ── */
        .terminal-line { opacity: 0; animation: slide-up 0.4s ease-out both; }
        .terminal-line:nth-child(1) { animation-delay: 0.1s; }
        .terminal-line:nth-child(2) { animation-delay: 0.4s; }
        .terminal-line:nth-child(3) { animation-delay: 0.7s; }
        .terminal-line:nth-child(4) { animation-delay: 1.2s; }
        .terminal-line:nth-child(5) { animation-delay: 1.5s; }
        .terminal-line:nth-child(6) { animation-delay: 1.8s; }
        .terminal-line:nth-child(7) { animation-delay: 2.3s; }
        .terminal-line:nth-child(8) { animation-delay: 2.6s; }
        .terminal-line:nth-child(9) { animation-delay: 2.9s; }

        /* ── Gradient borders ── */
        .gradient-border {
            position: relative;
            background: rgba(255,255,255,0.03);
        }
        .gradient-border::before {
            content: '';
            position: absolute; inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(135deg, rgba(99,102,241,0.5), rgba(168,85,247,0.5), rgba(6,182,212,0.3));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }

        /* ── Badge shimmer ── */
        .badge-shimmer {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            background-size: 400px 100%;
            animation: shimmer 2.5s infinite;
        }

        /* ── Stat number ── */
        .stat-number {
            background: linear-gradient(135deg, #ffffff, #a5b4fc);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
    </style>
</head>
<body class="bg-[#06060f] text-white overflow-x-hidden">

{{-- ═══════════════════════════════════════════
     NAVIGATION
════════════════════════════════════════════ --}}
<nav id="navbar" class="fixed top-0 inset-x-0 z-50 transition-all duration-300 border-b border-transparent">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 flex items-center justify-between h-16">

        {{-- Logo --}}
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 group">
            <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 via-violet-600 to-purple-700 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-indigo-500/40 transition-shadow">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span class="font-bold text-lg tracking-tight text-white">Outreach<span class="text-indigo-400">AI</span></span>
        </a>

        {{-- Center links (desktop) --}}
        <div class="hidden md:flex items-center gap-7 text-sm font-medium text-gray-400">
            <a href="#features"    class="hover:text-white transition-colors duration-200">Features</a>
            <a href="#ai-powers"  class="hover:text-white transition-colors duration-200">AI Integration</a>
            <a href="#roadmap"    class="hover:text-white transition-colors duration-200">Roadmap</a>
        </div>

        {{-- CTA --}}
        <div class="flex items-center gap-3">
            @guest
            <a href="{{ route('login') }}" class="hidden sm:block text-sm text-gray-400 hover:text-white transition-colors px-3 py-1.5">Sign in</a>
            <a href="{{ route('register') }}" class="text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-xl transition-all duration-200 shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40">
                Get started free
            </a>
            @endguest
            @auth
            <a href="{{ url('/dashboard') }}" class="text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-xl transition-all duration-200 flex items-center gap-2">
                Dashboard
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endauth
        </div>
    </div>
</nav>

{{-- ═══════════════════════════════════════════
     HERO
════════════════════════════════════════════ --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden grid-bg pt-16">
    {{-- Mesh blobs --}}
    <div aria-hidden="true">
        <div class="blob blob-1 w-[600px] h-[600px] top-[-100px] left-[-150px] float-1 opacity-60"></div>
        <div class="blob blob-2 w-[500px] h-[500px] top-[200px] right-[-100px] float-2 opacity-50"></div>
        <div class="blob blob-3 w-[400px] h-[400px] bottom-[-50px] left-[40%] float-3 opacity-40"></div>
        <div class="blob blob-4 w-[300px] h-[300px] top-[40%] left-[30%] float-1 opacity-30"></div>
    </div>

    <div class="relative max-w-5xl mx-auto px-5 sm:px-8 text-center py-20">

        {{-- Live badge --}}
        <div class="inline-flex items-center gap-2.5 glass rounded-full px-4 py-2 text-sm text-indigo-300 mb-8 border border-indigo-500/20 overflow-hidden">
            <div class="absolute inset-0 badge-shimmer pointer-events-none"></div>
            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full pulse-dot flex-shrink-0"></span>
            <span class="relative font-medium">GPT Actions · MCP · n8n Integration — <span class="text-emerald-400">Live Now</span></span>
        </div>

        {{-- Headline --}}
        <h1 class="text-5xl sm:text-6xl md:text-7xl font-black leading-[1.05] tracking-tight mb-6">
            Your outreach pipeline,<br>
            <span class="gradient-text">supercharged by AI</span>
        </h1>

        {{-- Sub-headline --}}
        <p class="text-lg sm:text-xl text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed font-light">
            Track scholarships, jobs &amp; research grants. Let GPT draft personalized emails.
            Schedule smart follow-ups. Land your next opportunity — without the chaos.
        </p>

        {{-- CTAs --}}
        <div class="flex flex-col sm:flex-row gap-3 justify-center mb-16">
            @guest
            <a href="{{ route('register') }}" class="group inline-flex items-center justify-center gap-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-8 py-4 rounded-2xl text-base transition-all duration-200 shadow-xl shadow-indigo-500/30 hover:shadow-indigo-500/50 glow-pulse">
                Start for free
                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 glass glass-hover text-white font-medium px-8 py-4 rounded-2xl text-base transition-all duration-200">
                Sign in
            </a>
            @endguest
            @auth
            <a href="{{ url('/dashboard') }}" class="group inline-flex items-center justify-center gap-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-8 py-4 rounded-2xl text-base transition-all duration-200 shadow-xl shadow-indigo-500/30">
                Go to Dashboard
                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            @endauth
        </div>

        {{-- Stats --}}
        <div class="flex flex-wrap items-center justify-center gap-x-10 gap-y-6 text-center">
            <div>
                <div class="text-3xl font-black stat-number">5+</div>
                <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wider">Opportunity types</div>
            </div>
            <div class="w-px h-10 bg-white/10 hidden sm:block"></div>
            <div>
                <div class="text-3xl font-black stat-number">GPT-4o</div>
                <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wider">AI backbone</div>
            </div>
            <div class="w-px h-10 bg-white/10 hidden sm:block"></div>
            <div>
                <div class="text-3xl font-black stat-number">0</div>
                <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wider">Auto-sends ever</div>
            </div>
            <div class="w-px h-10 bg-white/10 hidden sm:block"></div>
            <div>
                <div class="text-3xl font-black stat-number">17</div>
                <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wider">API endpoints</div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════
     FEATURES
════════════════════════════════════════════ --}}
<section id="features" class="py-28 px-5 sm:px-8 relative">
    <div class="max-w-6xl mx-auto">

        <div class="text-center mb-16">
            <p class="text-indigo-400 font-semibold text-xs uppercase tracking-[0.2em] mb-3">Everything you need</p>
            <h2 class="text-4xl md:text-5xl font-black text-white mb-5 tracking-tight">Built for serious outreach</h2>
            <p class="text-gray-400 text-lg max-w-xl mx-auto font-light">
                A complete CRM engineered for researchers, PhD applicants, job seekers, and ambitious professionals.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

            @php
            $features = [
                ['color'=>'indigo','icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01','title'=>'Opportunity Tracking','desc'=>'Track jobs, scholarships, grants, and research positions through every stage — from draft to offer, with deadline alerts.'],
                ['color'=>'violet','icon'=>'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z','title'=>'AI Email Drafts','desc'=>'GPT writes personalized outreach for each contact and opportunity. You review, edit, and send. Always in control.'],
                ['color'=>'cyan','icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z','title'=>'Smart Follow-ups','desc'=>'Reminder-only follow-ups that alert you at the right time. Never miss a deadline or a window to follow up.'],
                ['color'=>'emerald','icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z','title'=>'Contact Management','desc'=>'Manage professors, hiring managers, and programme directors. Automatic suppression of bounced or unsubscribed contacts.'],
                ['color'=>'amber','icon'=>'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12','title'=>'Bulk CSV Import','desc'=>'Import hundreds of contacts or opportunities from spreadsheets in seconds. Smart deduplication keeps data pristine.'],
                ['color'=>'rose','icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z','title'=>'Pipeline Analytics','desc'=>'Visualize your outreach funnel, track reply rates, and understand which approaches move the needle.'],
            ];
            @endphp

            @foreach($features as $f)
            <div class="glass rounded-2xl p-6 feature-card glass-hover cursor-default">
                <div class="icon-wrap w-12 h-12 bg-{{ $f['color'] }}-500/15 rounded-xl flex items-center justify-center mb-5 border border-{{ $f['color'] }}-500/20">
                    <svg class="w-6 h-6 text-{{ $f['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $f['icon'] }}"/>
                    </svg>
                </div>
                <h3 class="text-white font-bold text-lg mb-2">{{ $f['title'] }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════
     AI INTEGRATION
════════════════════════════════════════════ --}}
<section id="ai-powers" class="py-28 px-5 sm:px-8 relative overflow-hidden">
    <div aria-hidden="true">
        <div class="blob blob-2 w-[600px] h-[600px] top-0 right-[-200px] opacity-30"></div>
    </div>

    <div class="max-w-6xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

            {{-- Left text --}}
            <div>
                <p class="text-violet-400 font-semibold text-xs uppercase tracking-[0.2em] mb-3">AI-First Architecture</p>
                <h2 class="text-4xl md:text-5xl font-black text-white mb-6 leading-tight tracking-tight">
                    Talk to your CRM<br>like it's <span class="gradient-text">ChatGPT</span>
                </h2>
                <p class="text-gray-400 text-lg mb-10 leading-relaxed font-light">
                    OutreachAI ships with native GPT Actions and MCP (Model Context Protocol) support. Ask your AI to find overdue follow-ups, draft an email, or ingest 50 opportunities from a scraper — all via natural language.
                </p>

                <div class="space-y-5">
                    @php $bullets = [
                        ['color'=>'violet','title'=>'Custom GPT Actions','desc'=>'Use your CRM directly inside ChatGPT with a scoped, revokable API key. 17 endpoints, fully described in OpenAPI 3.1.'],
                        ['color'=>'indigo','title'=>'MCP Server for Claude','desc'=>'Connect via Model Context Protocol for tool-use and resource access in Claude Desktop, Cursor, and other MCP clients.'],
                        ['color'=>'cyan','title'=>'Zero auto-sends — ever','desc'=>'AI drafts. You review. You send. Your professional reputation stays completely in your hands.'],
                        ['color'=>'emerald','title'=>'n8n &amp; automation-ready','desc'=>'Bulk ingest from scrapers, n8n pipelines, or any automation tool via the /ingestion endpoints.'],
                    ]; @endphp
                    @foreach($bullets as $b)
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 bg-{{ $b['color'] }}-500/15 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5 border border-{{ $b['color'] }}-500/20">
                            <svg class="w-4 h-4 text-{{ $b['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-white font-semibold text-sm">{!! $b['title'] !!}</p>
                            <p class="text-gray-400 text-sm mt-0.5 leading-relaxed">{{ $b['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Right: terminal mockup --}}
            <div class="gradient-border rounded-2xl p-1 shadow-2xl shadow-indigo-500/10">
                <div class="bg-[#0c0c1a] rounded-xl p-6 font-mono text-sm overflow-hidden">
                    <div class="flex items-center gap-2 mb-5">
                        <div class="w-3 h-3 bg-red-500/80 rounded-full"></div>
                        <div class="w-3 h-3 bg-yellow-500/80 rounded-full"></div>
                        <div class="w-3 h-3 bg-emerald-500/80 rounded-full"></div>
                        <span class="ml-3 text-gray-600 text-xs font-sans">OutreachAI · GPT Actions API</span>
                    </div>
                    <div class="space-y-2 leading-relaxed">
                        <div class="terminal-line text-gray-500 text-xs">// Natural language → API call</div>
                        <div class="terminal-line text-indigo-300 text-sm">"Find my follow-ups due this week"</div>
                        <div class="terminal-line text-gray-600 text-xs pl-3">↳ GET /api/gpt/v1/follow-ups/due</div>
                        <div class="terminal-line text-emerald-400 text-xs pl-3">✓ 3 follow-ups due today</div>
                        <div class="terminal-line text-gray-700 text-xs mt-2">──────────────────────────────</div>
                        <div class="terminal-line text-violet-300 text-sm">"Draft outreach to Prof. Smith<br>&nbsp;&nbsp;for the Oxford DPhil"</div>
                        <div class="terminal-line text-gray-600 text-xs pl-3">↳ POST /api/gpt/v1/email-drafts</div>
                        <div class="terminal-line text-emerald-400 text-xs pl-3">✓ Draft saved — awaiting your review</div>
                        <div class="terminal-line text-gray-700 text-xs mt-2">──────────────────────────────</div>
                        <div class="terminal-line text-cyan-300 text-sm">"Ingest these 30 scholarship listings"</div>
                        <div class="terminal-line text-gray-600 text-xs pl-3">↳ POST /api/gpt/v1/ingestion/opportunities</div>
                        <div class="terminal-line text-emerald-400 text-xs pl-3">✓ 28 created · 2 duplicates skipped</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════
     ROADMAP
════════════════════════════════════════════ --}}
<section id="roadmap" class="py-28 px-5 sm:px-8">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <p class="text-cyan-400 font-semibold text-xs uppercase tracking-[0.2em] mb-3">What's coming</p>
            <h2 class="text-4xl md:text-5xl font-black text-white mb-5 tracking-tight">
                Built to <span class="gradient-text-warm">grow with you</span>
            </h2>
            <p class="text-gray-400 text-lg max-w-xl mx-auto font-light">
                The roadmap is ambitious. We're building the most intelligent personal outreach tool ever made.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @php $roadmap = [
                ['border'=>'border-indigo-500','icon_bg'=>'bg-indigo-500/15','icon_border'=>'border-indigo-500/20','icon_color'=>'text-indigo-400','badge_bg'=>'bg-indigo-500/10','badge_text'=>'text-indigo-300','badge_border'=>'border-indigo-500/20','eta'=>'Q3 2026',
                 'title'=>'AI Recommender System',
                 'desc'=>'Personalized opportunity suggestions based on your profile, past applications, and success patterns. The system learns what wins for you and surfaces more of it.',
                 'icon'=>'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
                ['border'=>'border-violet-500','icon_bg'=>'bg-violet-500/15','icon_border'=>'border-violet-500/20','icon_color'=>'text-violet-400','badge_bg'=>'bg-violet-500/10','badge_text'=>'text-violet-300','badge_border'=>'border-violet-500/20','eta'=>'Q3 2026',
                 'title'=>'Smart Ingestion Pipelines',
                 'desc'=>'Auto-scrape from LinkedIn Jobs, ScholarshipDb, ResearchGate, Euraxess, and more. Opportunities flow in automatically, deduplicated and ready for review.',
                 'icon'=>'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4'],
                ['border'=>'border-cyan-500','icon_bg'=>'bg-cyan-500/15','icon_border'=>'border-cyan-500/20','icon_color'=>'text-cyan-400','badge_bg'=>'bg-cyan-500/10','badge_text'=>'text-cyan-300','badge_border'=>'border-cyan-500/20','eta'=>'Q4 2026',
                 'title'=>'LinkedIn Integration',
                 'desc'=>'Import connections, sync profiles, enrich contacts with one click, and draft LinkedIn InMails directly from your CRM. Full OAuth, no screen scraping.',
                 'icon'=>'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
                ['border'=>'border-emerald-500','icon_bg'=>'bg-emerald-500/15','icon_border'=>'border-emerald-500/20','icon_color'=>'text-emerald-400','badge_bg'=>'bg-emerald-500/10','badge_text'=>'text-emerald-300','badge_border'=>'border-emerald-500/20','eta'=>'Q4 2026',
                 'title'=>'Success Analytics',
                 'desc'=>'Response rate dashboards, A/B email testing, optimal send-time suggestions, and funnel visualization — so you know exactly what\'s working.',
                 'icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                ['border'=>'border-amber-500','icon_bg'=>'bg-amber-500/15','icon_border'=>'border-amber-500/20','icon_color'=>'text-amber-400','badge_bg'=>'bg-amber-500/10','badge_text'=>'text-amber-300','badge_border'=>'border-amber-500/20','eta'=>'Q1 2027',
                 'title'=>'Smart Email Scheduling',
                 'desc'=>'Schedule approved emails for optimal delivery. AI suggests the best time per recipient based on timezone, engagement history, and open-rate patterns.',
                 'icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['border'=>'border-rose-500','icon_bg'=>'bg-rose-500/15','icon_border'=>'border-rose-500/20','icon_color'=>'text-rose-400','badge_bg'=>'bg-rose-500/10','badge_text'=>'text-rose-300','badge_border'=>'border-rose-500/20','eta'=>'Q1 2027',
                 'title'=>'AI Reply Analysis',
                 'desc'=>'Automatic sentiment analysis on inbound replies. Suggested responses, conversation health scoring, and smart re-engagement nudges.',
                 'icon'=>'M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z'],
                ['border'=>'border-purple-500','icon_bg'=>'bg-purple-500/15','icon_border'=>'border-purple-500/20','icon_color'=>'text-purple-400','badge_bg'=>'bg-purple-500/10','badge_text'=>'text-purple-300','badge_border'=>'border-purple-500/20','eta'=>'Q2 2027',
                 'title'=>'Team Collaboration',
                 'desc'=>'Share pipelines with mentors, supervisors, or career coaches. Multi-user workspaces with granular role-based access and shared templates.',
                 'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ['border'=>'border-sky-500','icon_bg'=>'bg-sky-500/15','icon_border'=>'border-sky-500/20','icon_color'=>'text-sky-400','badge_bg'=>'bg-sky-500/10','badge_text'=>'text-sky-300','badge_border'=>'border-sky-500/20','eta'=>'Q3 2027',
                 'title'=>'Multi-channel Outreach',
                 'desc'=>'Reach via email, LinkedIn DM, Twitter/X, and WhatsApp — all tracked in one unified thread per contact. One conversation, one history.',
                 'icon'=>'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ]; @endphp

            @foreach($roadmap as $r)
            <div class="glass rounded-2xl p-6 roadmap-card {{ $r['border'] }}">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 {{ $r['icon_bg'] }} rounded-xl flex items-center justify-center flex-shrink-0 border {{ $r['icon_border'] }}">
                        <svg class="w-5 h-5 {{ $r['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $r['icon'] }}"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center flex-wrap gap-2 mb-2">
                            <h3 class="text-white font-bold text-base">{{ $r['title'] }}</h3>
                            <span class="text-xs {{ $r['badge_bg'] }} {{ $r['badge_text'] }} px-2 py-0.5 rounded-full border {{ $r['badge_border'] }} font-medium">{{ $r['eta'] }}</span>
                        </div>
                        <p class="text-gray-400 text-sm leading-relaxed">{{ $r['desc'] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════
     FINAL CTA
════════════════════════════════════════════ --}}
<section class="py-28 px-5 sm:px-8">
    <div class="max-w-4xl mx-auto">
        <div class="gradient-border rounded-3xl p-12 text-center relative overflow-hidden">
            <div aria-hidden="true">
                <div class="blob blob-1 w-[400px] h-[400px] top-[-100px] left-[-100px] opacity-40"></div>
                <div class="blob blob-2 w-[350px] h-[350px] bottom-[-80px] right-[-80px] opacity-30"></div>
            </div>
            <div class="relative">
                <h2 class="text-4xl md:text-5xl font-black text-white mb-4 leading-tight tracking-tight">
                    Ready to land your<br>
                    <span class="gradient-text">next opportunity?</span>
                </h2>
                <p class="text-gray-400 text-lg mb-10 max-w-md mx-auto font-light leading-relaxed">
                    Join researchers, PhD applicants, and ambitious professionals who manage their outreach with AI.
                </p>
                @guest
                <a href="{{ route('register') }}" class="inline-flex items-center gap-2.5 bg-white text-gray-900 font-bold px-10 py-4 rounded-2xl text-base hover:bg-indigo-50 transition-colors shadow-2xl">
                    Create your free account
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
                @endguest
                @auth
                <a href="{{ url('/dashboard') }}" class="inline-flex items-center gap-2.5 bg-white text-gray-900 font-bold px-10 py-4 rounded-2xl text-base hover:bg-indigo-50 transition-colors shadow-2xl">
                    Go to Dashboard
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                @endauth
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════
     FOOTER
════════════════════════════════════════════ --}}
<footer class="border-t border-white/5 py-10 px-5 sm:px-8">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-3">
            <div class="w-7 h-7 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span class="font-bold text-white">Outreach<span class="text-indigo-400">AI</span></span>
            <span class="text-gray-700 text-sm">— Personal Outreach CRM &copy; {{ date('Y') }}</span>
        </div>
        <div class="flex items-center gap-6 text-sm text-gray-500">
            <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">Privacy Policy</a>
            <span class="text-gray-700">·</span>
            <a href="{{ route('terms') }}" class="hover:text-white transition-colors">Terms of Service</a>
            <span class="text-gray-700">·</span>
            <a href="{{ route('login') }}" class="hover:text-white transition-colors">Sign in</a>
        </div>
    </div>
</footer>

<script>
    // Sticky nav glass effect on scroll
    const nav = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
            nav.classList.add('nav-scrolled');
        } else {
            nav.classList.remove('nav-scrolled');
        }
    });
</script>
</body>
</html>
