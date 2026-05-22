<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // 1. Job Application Initial Outreach
            [
                'user_id'    => 1,
                'name'       => 'Job Application Initial Outreach',
                'type'       => 'initial_outreach',
                'subject'    => 'Application for {{position}} at {{company}}',
                'body'       => <<<'HTML'
<p>Dear {{first_name}},</p>

<p>I hope this message finds you well. My name is {{your_name}}, and I am writing to express my strong interest in the {{position}} role at {{company}}. After researching your organization extensively, I am confident that my background and skills would make a meaningful contribution to your team.</p>

<p>Throughout my career I have developed expertise in areas that align closely with the requirements you are seeking. I am particularly excited about {{company}}'s mission and the innovative work being done in this space, and I believe this opportunity would allow me to both contribute significantly and continue to grow professionally.</p>

<p>I have attached my resume and portfolio for your review. I would welcome the opportunity to discuss how my experience and enthusiasm could benefit {{company}}. Please feel free to reach out at your earliest convenience — I am flexible and happy to schedule a call or meeting at a time that suits you.</p>

<p>Thank you for considering my application. I look forward to hearing from you.</p>

<p>Warm regards,<br>{{your_name}}</p>
HTML,
                'variables'  => ['first_name', 'position', 'company', 'your_name'],
                'is_active'  => true,
                'times_used' => 0,
            ],

            // 2. Research Collaboration Request
            [
                'user_id'    => 1,
                'name'       => 'Research Collaboration Request',
                'type'       => 'initial_outreach',
                'subject'    => 'Research Collaboration Inquiry - {{research_topic}}',
                'body'       => <<<'HTML'
<p>Dear {{first_name}},</p>

<p>I hope this email finds you well. I am reaching out because I have been following your work on {{research_topic}} with great interest, and I believe there is significant potential for a productive collaboration between our research groups.</p>

<p>My own research focuses on similar questions, and I have been exploring approaches that complement the methodology described in your recent publications. I think a joint effort could yield insights that neither of us could achieve independently, and I am genuinely excited about the possibilities that such a partnership might open up.</p>

<p>I would love the opportunity to schedule a brief call to explore potential areas of overlap and discuss whether a collaboration might be mutually beneficial. I am happy to share preliminary findings and ideas in advance if that would be helpful.</p>

<p>Thank you for your time and for the inspiring work you have already contributed to this field. I look forward to your response.</p>

<p>Best regards,<br>{{your_name}}</p>
HTML,
                'variables'  => ['first_name', 'research_topic', 'your_name'],
                'is_active'  => true,
                'times_used' => 0,
            ],

            // 3. Scholarship Application Follow-up
            [
                'user_id'    => 1,
                'name'       => 'Scholarship Application Follow-up',
                'type'       => 'follow_up',
                'subject'    => 'Following up: {{scholarship_name}} Application',
                'body'       => <<<'HTML'
<p>Dear {{first_name}},</p>

<p>I hope you are well. I am writing to kindly follow up on my application for the {{scholarship_name}}. I submitted my application some time ago and wanted to check whether there are any updates regarding the review process or whether any additional information is needed from my side.</p>

<p>I remain very enthusiastic about this opportunity and am confident that I would make the most of the support it would provide. Please do not hesitate to let me know if there is anything further I can supply to assist with the evaluation.</p>

<p>Thank you sincerely for your time and consideration. I look forward to hearing from you.</p>

<p>Kind regards,<br>{{your_name}}</p>
HTML,
                'variables'  => ['first_name', 'scholarship_name', 'your_name'],
                'is_active'  => true,
                'times_used' => 0,
            ],

            // 4. Networking Intro Email
            [
                'user_id'    => 1,
                'name'       => 'Networking Intro Email',
                'type'       => 'networking',
                'subject'    => 'Introduction - {{your_name}} from {{your_institution}}',
                'body'       => <<<'HTML'
<p>Dear {{first_name}},</p>

<p>I hope you don't mind me reaching out directly. My name is {{your_name}}, and I am currently at {{your_institution}} working in a field closely related to your own. I came across your profile and was immediately impressed by your background and accomplishments.</p>

<p>I am always eager to connect with thoughtful professionals who share similar interests, and I believe there could be great value in simply getting to know each other and exchanging perspectives. Whether it's a brief virtual coffee chat or an exchange of ideas over email, I would genuinely value the opportunity to connect.</p>

<p>I have no specific agenda beyond building a meaningful professional relationship — though I am always open to discussing collaboration, advice, or simply swapping notes on topics we both care about.</p>

<p>Thank you for your time, and I hope to hear from you soon.</p>

<p>Best,<br>{{your_name}}</p>
HTML,
                'variables'  => ['first_name', 'your_name', 'your_institution'],
                'is_active'  => true,
                'times_used' => 0,
            ],

            // 5. Job Application Follow-up
            [
                'user_id'    => 1,
                'name'       => 'Job Application Follow-up',
                'type'       => 'follow_up',
                'subject'    => 'Follow-up: Application for {{position}} at {{company}}',
                'body'       => <<<'HTML'
<p>Dear {{first_name}},</p>

<p>I hope you are having a great week. I wanted to briefly follow up on the application I submitted for the {{position}} role at {{company}} approximately {{days_ago}} days ago.</p>

<p>I remain very interested in this opportunity and in contributing to the work your team is doing. If you need any additional information or samples of my work, I am happy to provide them promptly.</p>

<p>I understand that hiring processes can take time, and I appreciate your consideration. Please feel free to reach out if there is anything I can do to move the process forward.</p>

<p>Thank you again for your time.</p>

<p>Best regards,<br>{{your_name}}</p>
HTML,
                'variables'  => ['first_name', 'position', 'company', 'days_ago', 'your_name'],
                'is_active'  => true,
                'times_used' => 0,
            ],

            // 6. Grant Funding Inquiry
            [
                'user_id'    => 1,
                'name'       => 'Grant Funding Inquiry',
                'type'       => 'initial_outreach',
                'subject'    => 'Grant Funding Inquiry - {{project_name}}',
                'body'       => <<<'HTML'
<p>Dear {{first_name}},</p>

<p>I am writing to inquire about grant funding opportunities that may be available for {{project_name}}. Having reviewed your organization's funding priorities and past grants, I believe our project aligns strongly with your mission and the outcomes you seek to support.</p>

<p>{{project_name}} aims to address a significant challenge in its field through an innovative, evidence-based approach. We have assembled a multidisciplinary team with the expertise and commitment required to deliver meaningful, measurable results within a realistic timeframe and budget.</p>

<p>I would appreciate the opportunity to share a brief project summary and discuss whether our work might be a good fit for your current funding cycle. I am happy to prepare a formal letter of inquiry, provide preliminary data, or schedule a call at your convenience.</p>

<p>Thank you for your time and for the important work your organization does in supporting impactful initiatives. I look forward to the possibility of working together.</p>

<p>Sincerely,<br>{{your_name}}</p>
HTML,
                'variables'  => ['first_name', 'project_name', 'your_name'],
                'is_active'  => true,
                'times_used' => 0,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::create($template);
        }
    }
}
