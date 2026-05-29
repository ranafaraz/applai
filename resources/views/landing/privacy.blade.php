<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy — OutreachAI</title>
    <meta name="description" content="OutreachAI Privacy Policy — how we collect, use, and protect your data.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); }
        .prose-legal h2 { font-size: 1.25rem; font-weight: 700; color: #fff; margin-top: 2.5rem; margin-bottom: 0.75rem; }
        .prose-legal h3 { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin-top: 1.5rem; margin-bottom: 0.5rem; }
        .prose-legal p  { color: #94a3b8; line-height: 1.8; margin-bottom: 1rem; font-size: 0.9375rem; }
        .prose-legal ul { list-style: disc; padding-left: 1.5rem; color: #94a3b8; margin-bottom: 1rem; }
        .prose-legal ul li { margin-bottom: 0.35rem; font-size: 0.9375rem; line-height: 1.7; }
        .prose-legal a  { color: #818cf8; text-decoration: underline; }
        .prose-legal strong { color: #e2e8f0; font-weight: 600; }
        .toc-link { transition: color 0.15s; }
        .toc-link:hover { color: #a5b4fc; }
    </style>
</head>
<body class="bg-[#06060f] text-white">

{{-- NAV --}}
<nav class="sticky top-0 z-50 glass border-b border-white/5">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 flex items-center justify-between h-14">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5">
            <div class="w-7 h-7 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span class="font-bold text-white">Outreach<span class="text-indigo-400">AI</span></span>
        </a>
        <div class="flex items-center gap-4 text-sm text-gray-400">
            <a href="{{ route('terms') }}" class="hover:text-white transition-colors">Terms</a>
            @guest
            <a href="{{ route('login') }}" class="hover:text-white transition-colors">Sign in</a>
            @endguest
            @auth
            <a href="{{ url('/dashboard') }}" class="text-indigo-400 hover:text-indigo-300 transition-colors font-medium">Dashboard</a>
            @endauth
        </div>
    </div>
</nav>

{{-- HERO --}}
<div class="max-w-3xl mx-auto px-5 sm:px-8 pt-16 pb-4">
    <div class="inline-flex items-center gap-2 text-xs text-indigo-400 font-medium uppercase tracking-widest mb-6">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        Legal
    </div>
    <h1 class="text-4xl md:text-5xl font-black text-white mb-4 tracking-tight">Privacy Policy</h1>
    <p class="text-gray-400 text-lg font-light mb-2">How OutreachAI collects, uses, and protects your personal data.</p>
    <p class="text-gray-600 text-sm">Last updated: <strong class="text-gray-500">{{ date('F j, Y') }}</strong> &nbsp;·&nbsp; Effective: <strong class="text-gray-500">{{ date('F j, Y') }}</strong></p>
</div>

{{-- CONTENT --}}
<div class="max-w-3xl mx-auto px-5 sm:px-8 py-12">
    <div class="glass rounded-2xl p-8 sm:p-10 prose-legal">

        <p>
            This Privacy Policy describes how <strong>OutreachAI</strong> ("we", "our", "us"), operated by Rana Faraz Ahmed
            (<a href="mailto:ranafarazahmed@gmail.com">ranafarazahmed@gmail.com</a>), collects, uses, and protects information
            when you use the OutreachAI personal outreach CRM platform, available at
            <a href="https://crm.dexdevs.com">crm.dexdevs.com</a> and associated API endpoints (collectively, the "Service").
        </p>
        <p>
            By using the Service, you agree to the practices described in this policy. If you do not agree, please discontinue use.
        </p>

        <h2>1. Information We Collect</h2>

        <h3>1.1 Account Information</h3>
        <p>When you create an account, we collect:</p>
        <ul>
            <li>Your name and email address</li>
            <li>A hashed version of your password (we never store plaintext passwords)</li>
            <li>Optional profile information you provide</li>
        </ul>

        <h3>1.2 CRM Data You Enter</h3>
        <p>We store data you deliberately enter into the Service, including:</p>
        <ul>
            <li><strong>Contacts</strong> — names, email addresses, phone numbers, job titles, LinkedIn URLs, notes</li>
            <li><strong>Opportunities</strong> — opportunity titles, organizations, descriptions, deadlines, URLs, status, priority, notes</li>
            <li><strong>Email drafts and messages</strong> — subject lines, body text, recipients</li>
            <li><strong>Follow-up reminders</strong> — scheduled dates and notes</li>
            <li><strong>Email account credentials</strong> — SMTP/IMAP settings stored in encrypted form</li>
        </ul>

        <h3>1.3 API Usage Data</h3>
        <p>
            When you or your AI integrations (GPT Actions, MCP clients, n8n) call our API, we log:
            request timestamps, endpoints accessed, response status codes, IP addresses, and
            <strong>field names only</strong> (never field values) for audit purposes.
        </p>

        <h3>1.4 Technical Data</h3>
        <ul>
            <li>Server-side logs including IP addresses and User-Agent strings</li>
            <li>Error logs for debugging purposes</li>
            <li>Timestamps of account activity</li>
        </ul>

        <h3>1.5 No Tracking Cookies</h3>
        <p>
            We do not use third-party analytics, advertising cookies, or cross-site tracking.
            Session cookies are used solely to maintain your authenticated session.
        </p>

        <h2>2. How We Use Your Information</h2>
        <p>We use your information exclusively to:</p>
        <ul>
            <li>Provide and operate the Service</li>
            <li>Authenticate your identity and maintain your session</li>
            <li>Process your CRM data and display it back to you</li>
            <li>Send emails via your configured SMTP account (only when you explicitly trigger sending)</li>
            <li>Detect and prevent abuse, fraud, and unauthorized access</li>
            <li>Debug errors and improve service reliability</li>
            <li>Comply with legal obligations</li>
        </ul>
        <p>
            <strong>We do not sell your data.</strong> We do not use your data for advertising.
            We do not share your data with third parties except as described in Section 4.
        </p>

        <h2>3. AI Features &amp; Data Processing</h2>
        <p>
            OutreachAI integrates with external AI services (such as OpenAI's ChatGPT) via a GPT Actions
            schema and MCP protocol. When you use these integrations:
        </p>
        <ul>
            <li>Your AI client (e.g., ChatGPT) communicates with our API using your API key</li>
            <li>Data returned by our API (contact names, opportunity titles, etc.) is processed by the AI provider you have chosen</li>
            <li>We do not send your data to any AI provider ourselves — your client does, under your control</li>
            <li>Email drafts created via AI are <strong>never sent automatically</strong> — you must explicitly approve and send them from the CRM interface</li>
            <li>API audit logs record <em>which</em> actions were taken but not the full content of messages</li>
        </ul>
        <p>
            Please review the privacy policy of any AI provider you connect (e.g.,
            <a href="https://openai.com/privacy" target="_blank" rel="noopener">OpenAI Privacy Policy</a>,
            <a href="https://www.anthropic.com/privacy" target="_blank" rel="noopener">Anthropic Privacy Policy</a>).
        </p>

        <h2>4. Data Sharing</h2>
        <p>We do not sell, rent, or trade your personal information. We may share data only in these limited circumstances:</p>
        <ul>
            <li><strong>Infrastructure providers:</strong> Our hosting provider (VPS server) processes data on our behalf. The server is located in North America/EU.</li>
            <li><strong>Legal requirements:</strong> If required by applicable law, court order, or governmental authority.</li>
            <li><strong>Safety:</strong> To protect the rights, property, or safety of OutreachAI, our users, or others.</li>
        </ul>

        <h2>5. Data Retention</h2>
        <p>
            Your CRM data is retained for as long as your account exists. You may delete individual records at any time
            within the application. To request deletion of your entire account and all associated data, contact us at
            <a href="mailto:ranafarazahmed@gmail.com">ranafarazahmed@gmail.com</a>.
            We will process deletion requests within 30 days.
        </p>
        <p>
            Audit logs and server logs may be retained for up to 90 days for security and debugging purposes,
            after which they are permanently deleted.
        </p>

        <h2>6. Data Security</h2>
        <p>We implement industry-standard security measures:</p>
        <ul>
            <li>All data in transit is encrypted via TLS/HTTPS</li>
            <li>Passwords are hashed using bcrypt — plaintext passwords are never stored</li>
            <li>API keys are stored as SHA-256 hashes — raw tokens are shown only once</li>
            <li>Email account passwords are stored using AES-256 encryption</li>
            <li>API access is scoped — each token grants only the permissions you configure</li>
            <li>IP allowlists are supported for API clients</li>
        </ul>
        <p>
            No system is 100% secure. In the event of a data breach that affects your personal information,
            we will notify you within 72 hours of becoming aware, in accordance with applicable law.
        </p>

        <h2>7. Your Rights (GDPR &amp; Similar)</h2>
        <p>
            Depending on your jurisdiction, you may have the following rights regarding your personal data:
        </p>
        <ul>
            <li><strong>Access:</strong> Request a copy of the personal data we hold about you</li>
            <li><strong>Rectification:</strong> Correct inaccurate or incomplete data</li>
            <li><strong>Erasure:</strong> Request deletion of your data ("right to be forgotten")</li>
            <li><strong>Portability:</strong> Receive your data in a structured, machine-readable format</li>
            <li><strong>Restriction:</strong> Request that we restrict processing of your data</li>
            <li><strong>Objection:</strong> Object to processing based on legitimate interests</li>
            <li><strong>Withdrawal of consent:</strong> Where processing is based on consent, withdraw it at any time</li>
        </ul>
        <p>
            To exercise any of these rights, email <a href="mailto:ranafarazahmed@gmail.com">ranafarazahmed@gmail.com</a>.
            We will respond within 30 days.
        </p>

        <h2>8. Cookies</h2>
        <p>
            OutreachAI uses only essential cookies:
        </p>
        <ul>
            <li><strong>Session cookie:</strong> Maintains your authenticated session. Deleted when you log out or close your browser.</li>
            <li><strong>CSRF token cookie:</strong> Protects against cross-site request forgery attacks.</li>
        </ul>
        <p>
            We do not use advertising cookies, analytics cookies, or any third-party tracking technologies.
        </p>

        <h2>9. Children's Privacy</h2>
        <p>
            OutreachAI is intended for users 16 years of age and older. We do not knowingly collect personal
            information from children under 16. If you believe a child under 16 has provided us with personal
            information, please contact us immediately.
        </p>

        <h2>10. Changes to This Policy</h2>
        <p>
            We may update this Privacy Policy from time to time. We will notify registered users of material
            changes by email or via an in-app notice. The "Last updated" date at the top of this page will
            always reflect the most recent revision.
        </p>

        <h2>11. Contact Us</h2>
        <p>
            For privacy-related questions, data requests, or concerns, contact:
        </p>
        <ul>
            <li><strong>Name:</strong> Rana Faraz Ahmed</li>
            <li><strong>Email:</strong> <a href="mailto:ranafarazahmed@gmail.com">ranafarazahmed@gmail.com</a></li>
            <li><strong>Website:</strong> <a href="https://crm.dexdevs.com">crm.dexdevs.com</a></li>
        </ul>

    </div>
</div>

{{-- FOOTER --}}
<footer class="border-t border-white/5 py-8 px-5 sm:px-8 mt-4">
    <div class="max-w-3xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
        <a href="{{ url('/') }}" class="flex items-center gap-2">
            <div class="w-6 h-6 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-md flex items-center justify-center">
                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <span class="font-semibold text-gray-400 text-sm">OutreachAI &copy; {{ date('Y') }}</span>
        </a>
        <div class="flex items-center gap-5 text-sm text-gray-600">
            <a href="{{ url('/') }}" class="hover:text-white transition-colors">Home</a>
            <a href="{{ route('terms') }}" class="hover:text-white transition-colors">Terms</a>
            <a href="mailto:ranafarazahmed@gmail.com" class="hover:text-white transition-colors">Contact</a>
        </div>
    </div>
</footer>

</body>
</html>
