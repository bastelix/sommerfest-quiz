<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\DomainContactTemplateService;
use Tests\TestCase;

class DomainContactTemplateServiceTest extends TestCase
{
    public function testSaveAndRetrieveTemplates(): void {
        $pdo = $this->createDatabase();
        $service = new DomainContactTemplateService($pdo);

        $service->save('www.example.com', [
            'sender_name' => 'Quiz Team',
            'recipient_html' => '<p>{{ name }}</p>',
            'recipient_text' => 'Name: {{ name }}',
            'sender_html' => '<strong>Danke {{ name }}</strong>',
            'sender_text' => 'Danke {{ name }}',
        ]);

        $template = $service->get('example.com');
        $this->assertNotNull($template);
        $this->assertSame('example.com', $template['domain']);
        $this->assertSame('Quiz Team', $template['sender_name']);
        $this->assertSame('<p>{{ name }}</p>', $template['recipient_html']);
        $this->assertSame('Name: {{ name }}', $template['recipient_text']);
        $this->assertSame('<strong>Danke {{ name }}</strong>', $template['sender_html']);
        $this->assertSame('Danke {{ name }}', $template['sender_text']);

        $service->save('example.com', [
            'sender_name' => '  ',
            'recipient_html' => '',
            'recipient_text' => null,
            'sender_html' => '<p>Updated</p>',
            'sender_text' => 'Updated',
        ]);

        $updated = $service->getForHost('admin.example.com');
        $this->assertNotNull($updated);
        $this->assertSame('example.com', $updated['domain']);
        $this->assertNull($updated['sender_name']);
        $this->assertNull($updated['recipient_html']);
        $this->assertNull($updated['recipient_text']);
        $this->assertSame('<p>Updated</p>', $updated['sender_html']);
        $this->assertSame('Updated', $updated['sender_text']);
    }
}
