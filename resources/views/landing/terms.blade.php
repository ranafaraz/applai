<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service — OutreachAI</title>
    <meta name="description" content="OutreachAI Terms of Service — the rules governing use of the OutreachAI personal outreach CRM.">
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
        .prose-legal ol { list-style: decimal; padding-left: 1.5rem; color: #94a3b8; margin-bottom: 1rem; }
        .prose-legal ol li { margin-bottom: 0.35rem; font-size: 0.9375rem; line-height: 1.7; }
        .prose-legal a  { color: #818cf8; text-decoration: underline; }
        .prose-legal strong { color: #e2e8f0; font-weight: 600; }
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
            <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">Privacy</a>
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
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Legal
    </div>
    <h1 class="text-4xl md:text-5xl font-black text-white mb-4 tracking-tight">Terms of Service</h1>
    <p class="text-gray-400 text-lg font-light mb-2">The rules governing your use of OutreachAI.</p>
    <p class="text-gray-600 text-sm">Last updated: <strong class="text-gray-500">{{ date('F j, Y') }}</strong> &nbsp;·&nbsp; Effective: <strong class="text-gray-500">{{ date('F j, Y') }}</strong></p>
</div>

{{-- CONTENT --}}
<div class="max-w-3xl mx-auto px-5 sm:px-8 py-12">
    <div class="glass rounded-2xl p-8 sm:p-10 prose-legal">

        <p>
            These Terms of Service ("Terms") govern your access to and use of the <strong>OutreachAI</strong> personal
            outreach CRM platform ("Service"), operated by Rana Faraz Ahmed ("we", "our", "us").
            By creating an account or using the Service, you agree to be bound by these Terms.
        </p>
        <p>
            If you do not agree with any part of these Terms, you must not use the Service.
        </p>

        <h2>1. The Service</h2>
        <p>
            OutreachAI is a personal outreach customer relationship management (CRM) tool that helps individuals
            track professional opportunities, manage contacts, draft emails, and schedule follow-ups. The Service
            includes a web application, REST API, GPT Actions schema, and MCP (Model Context Protocol) adapter.
        </p>

        <h2>2. Eligibility</h2>
        <p>You must:</p>
        <ul>
            <li>Be at least 16 years of age</li>
            <li>Have the legal capacity to enter into a binding contract</li>
            <li>Not be prohibited from using the Service under applicable law</li>
        </ul>
        <p>By using the Service, you represent and warrant that you meet these requirements.</p>

        <h2>3. Your Account</h2>
        <h3>3.1 Registration</h3>
        <p>
            You must provide accurate, complete information when creating your account. You are responsible for
            maintaining the confidentiality of your password and API keys. You are liable for all activity
            conducted through your account.
        </p>

        <h3>3.2 API Keys</h3>
        <p>
            API keys grant programmatic access to your CRM data. You must:
        </p>
        <ul>
            <li>Keep API keys confidential — treat them like passwords</li>
            <li>Not share keys with unauthorized parties</li>
            <li>Revoke compromised keys immediately via Settings → Integrations</li>
            <li>Use only the minimum scopes required for each integration</li>
        </ul>
        <p>You are responsible for all actions taken using your API keys.</p>

        <h3>3.3 Account Termination</h3>
        <p>
            You may delete your account at any time. We reserve the right to suspend or terminate accounts
            that violate these Terms, engage in abuse, or are inactive for an extended period, with reasonable notice.
        </p>

        <h2>4. Acceptable Use</h2>
        <h3>4.1 Permitted Use</h3>
        <p>You may use the Service for lawful personal or professional outreach, including job searching, academic networking, scholarship applications, and research collaboration.</p>

        <h3>4.2 Prohibited Use</h3>
        <p>You must not use the Service to:</p>
        <ul>
            <li>Send unsolicited bulk email (spam) or conduct mass-marketing campaigns</li>
            <li>Harass, threaten, or harm any individual</li>
            <li>Violate any applicable law, including anti-spam regulations (CAN-SPAM, GDPR, CASL)</li>
            <li>Store or transmit malicious code, viruses, or harmful software</li>
            <li>Attempt to gain unauthorized access to any system or user data</li>
            <li>Circumvent or reverse-engineer any security feature of the Service</li>
            <li>Use the Service to build a competing product without our written consent</li>
            <li>Impersonate any person or entity</li>
            <li>Violate intellectual property rights of any third party</li>
            <li>Process personal data in violation of applicable privacy law (including GDPR)</li>
        </ul>

        <h3>4.3 AI Features — Critical Restrictions</h3>
        <p>
            The AI email drafting feature creates draft emails for your review. You acknowledge that:
        </p>
        <ul>
            <li>AI-generated drafts are <strong>never sent automatically</strong> — you must explicitly review and send each email</li>
            <li>You are solely responsible for the content of any email you send, regardless of whether AI generated a draft</li>
            <li>You must ensure AI-generated content is accurate, appropriate, and compliant with applicable law before sending</li>
            <li>We are not liable for any consequences arising from emails you choose to send</li>
        </ul>

        <h2>5. Your Data</h2>
        <p>
            You retain ownership of all data you enter into the Service ("Your Data"). By using the Service,
            you grant us a limited, non-exclusive licence to store and process Your Data solely to provide
            and improve the Service. We will not use Your Data for any other purpose.
        </p>
        <p>
            You represent that you have all necessary rights and consents to store the personal data of your
            contacts in the Service, including compliance with applicable privacy laws (GDPR, CCPA, etc.).
            You are the data controller; we act as a data processor.
        </p>

        <h2>6. Intellectual Property</h2>
        <p>
            The Service, including its code, design, trademarks, and documentation, is the property of
            OutreachAI / Rana Faraz Ahmed and is protected by copyright and other intellectual property laws.
            You may not copy, modify, distribute, sell, or lease any part of the Service without our written consent.
        </p>
        <p>
            The OpenAPI schema at <code>/openapi/gpt-actions.json</code> is publicly accessible for the purpose
            of configuring Custom GPT Actions and MCP clients. This does not grant any broader licence to the Service.
        </p>

        <h2>7. Third-Party Integrations</h2>
        <p>
            The Service integrates with third-party AI providers (OpenAI, Anthropic, etc.), email servers,
            and automation tools (n8n, etc.). Your use of these integrations is also governed by the respective
            third-party terms and privacy policies. We are not responsible for the conduct of third-party services.
        </p>

        <h2>8. Disclaimer of Warranties</h2>
        <p>
            The Service is provided <strong>"as is"</strong> and <strong>"as available"</strong>, without warranties of any kind,
            express or implied. We do not warrant that the Service will be uninterrupted, error-free, or secure.
            We do not guarantee that AI-generated content will be accurate, suitable, or legally compliant.
        </p>

        <h2>9. Limitation of Liability</h2>
        <p>
            To the maximum extent permitted by applicable law, OutreachAI and its operators shall not be liable
            for any indirect, incidental, special, consequential, or punitive damages arising out of your use
            of the Service, including but not limited to damages arising from AI-generated content, emails sent
            through the Service, data loss, or unauthorized access.
        </p>
        <p>
            Our total liability to you for any claim shall not exceed the amount you paid us in the 12 months
            preceding the claim (or £50 if you have not paid us anything).
        </p>

        <h2>10. Privacy</h2>
        <p>
            Your use of the Service is also governed by our
            <a href="{{ route('privacy') }}">Privacy Policy</a>,
            which is incorporated into these Terms by reference.
        </p>

        <h2>11. Changes to Terms</h2>
        <p>
            We may update these Terms from time to time. We will notify you of material changes by email or
            in-app notice at least 14 days before they take effect. Continued use of the Service after
            changes take effect constitutes acceptance of the revised Terms.
        </p>

        <h2>12. Governing Law</h2>
        <p>
            These Terms are governed by the laws of Pakistan (and, where applicable, European Union law for
            EU-resident users). Any disputes shall be resolved in accordance with applicable law.
        </p>

        <h2>13. Contact</h2>
        <p>
            For questions about these Terms, contact:
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
            <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">Privacy</a>
            <a href="mailto:ranafarazahmed@gmail.com" class="hover:text-white transition-colors">Contact</a>
        </div>
    </div>
</footer>

</body>
</html>
