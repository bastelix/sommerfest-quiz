<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Domain\Ticket;
use App\Service\TicketService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class TicketServiceTest extends TestCase
{
    public function testCreateAndRetrieveTicket(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'test-ns',
            'Fix broken link',
            'The wiki link on page 3 is broken.',
            Ticket::TYPE_BUG,
            Ticket::PRIORITY_HIGH,
            Ticket::REFERENCE_WIKI_ARTICLE,
            42,
            'admin',
            ['qa', 'wiki'],
            '2026-04-01',
            'customer1'
        );

        $this->assertSame('Fix broken link', $ticket->getTitle());
        $this->assertSame('The wiki link on page 3 is broken.', $ticket->getDescription());
        $this->assertSame(Ticket::STATUS_OPEN, $ticket->getStatus());
        $this->assertSame(Ticket::PRIORITY_HIGH, $ticket->getPriority());
        $this->assertSame(Ticket::TYPE_BUG, $ticket->getType());
        $this->assertSame(Ticket::REFERENCE_WIKI_ARTICLE, $ticket->getReferenceType());
        $this->assertSame(42, $ticket->getReferenceId());
        $this->assertSame('admin', $ticket->getAssignee());
        $this->assertSame(['qa', 'wiki'], $ticket->getLabels());
        $this->assertSame('customer1', $ticket->getCreatedBy());
        $this->assertNotNull($ticket->getDueDate());

        $fetched = $service->getById($ticket->getId());
        $this->assertNotNull($fetched);
        $this->assertSame($ticket->getTitle(), $fetched->getTitle());
    }

    public function testListTicketsByNamespace(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $service->create(
            'ns-a', 'Ticket A', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );
        $service->create(
            'ns-b', 'Ticket B', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );
        $service->create(
            'ns-a', 'Ticket C', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );

        $nsA = $service->listByNamespace('ns-a');
        $this->assertCount(2, $nsA);

        $nsB = $service->listByNamespace('ns-b');
        $this->assertCount(1, $nsB);
        $this->assertSame('Ticket B', $nsB[0]->getTitle());
    }

    public function testListTicketsWithFilters(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $service->create(
            'ns', 'Bug ticket', '', Ticket::TYPE_BUG,
            Ticket::PRIORITY_HIGH, null, null, 'alice', [], null, null
        );
        $service->create(
            'ns', 'Task ticket', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_LOW, null, null, 'bob', [], null, null
        );
        $service->create(
            'ns', 'Review ticket', '', Ticket::TYPE_REVIEW,
            Ticket::PRIORITY_NORMAL, null, null, 'alice', [], null, null
        );

        $bugs = $service->listByNamespace('ns', ['type' => Ticket::TYPE_BUG]);
        $this->assertCount(1, $bugs);
        $this->assertSame('Bug ticket', $bugs[0]->getTitle());

        $aliceTickets = $service->listByNamespace('ns', ['assignee' => 'alice']);
        $this->assertCount(2, $aliceTickets);

        $highPriority = $service->listByNamespace('ns', ['priority' => Ticket::PRIORITY_HIGH]);
        $this->assertCount(1, $highPriority);
    }

    public function testStatusTransitionWorkflow(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'ns', 'Workflow test', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );
        $this->assertSame(Ticket::STATUS_OPEN, $ticket->getStatus());

        $ticket = $service->transition($ticket->getId(), Ticket::STATUS_IN_PROGRESS);
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->getStatus());

        $ticket = $service->transition($ticket->getId(), Ticket::STATUS_RESOLVED);
        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->getStatus());

        $ticket = $service->transition($ticket->getId(), Ticket::STATUS_CLOSED);
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->getStatus());
    }

    public function testInvalidStatusTransitionThrows(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'ns', 'Invalid transition', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot transition from "open" to "resolved"');
        $service->transition($ticket->getId(), Ticket::STATUS_RESOLVED);
    }

    public function testUpdateTicketFields(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'ns', 'Original title', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_LOW, null, null, null, [], null, null
        );

        $updated = $service->update($ticket->getId(), [
            'title' => 'Updated title',
            'priority' => Ticket::PRIORITY_CRITICAL,
            'assignee' => 'bob',
        ]);

        $this->assertSame('Updated title', $updated->getTitle());
        $this->assertSame(Ticket::PRIORITY_CRITICAL, $updated->getPriority());
        $this->assertSame('bob', $updated->getAssignee());
    }

    public function testDeleteTicket(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'ns', 'To delete', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );
        $service->delete($ticket->getId());

        $this->assertNull($service->getById($ticket->getId()));
    }

    public function testAddAndListComments(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'ns', 'With comments', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );

        $c1 = $service->addComment($ticket->getId(), 'alice', 'First comment');
        $c2 = $service->addComment($ticket->getId(), 'bob', 'Second comment');

        $comments = $service->listComments($ticket->getId());
        $this->assertCount(2, $comments);
        $this->assertSame('First comment', $comments[0]->getBody());
        $this->assertSame('alice', $comments[0]->getAuthor());
        $this->assertSame('Second comment', $comments[1]->getBody());
        $this->assertSame($ticket->getId(), $c1->getTicketId());
    }

    public function testDeleteComment(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'ns', 'Comment delete test', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );
        $comment = $service->addComment($ticket->getId(), 'alice', 'To be deleted');

        $service->deleteComment($comment->getId());

        $comments = $service->listComments($ticket->getId());
        $this->assertCount(0, $comments);
    }

    public function testCreateTicketWithReference(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'ns', 'Review article', '', Ticket::TYPE_REVIEW,
            Ticket::PRIORITY_NORMAL, Ticket::REFERENCE_PAGE, 99,
            null, [], null, null
        );

        $this->assertSame(Ticket::REFERENCE_PAGE, $ticket->getReferenceType());
        $this->assertSame(99, $ticket->getReferenceId());
    }

    public function testCreateTicketValidationEmptyTitle(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ticket title must not be empty');
        $service->create('ns', '', '', Ticket::TYPE_TASK, Ticket::PRIORITY_NORMAL, null, null, null, [], null, null);
    }

    public function testCreateTicketValidationInvalidType(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid ticket type');
        $service->create('ns', 'Bad type', '', 'invalid', Ticket::PRIORITY_NORMAL, null, null, null, [], null, null);
    }

    public function testCreateTicketValidationInvalidPriority(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid ticket priority');
        $service->create('ns', 'Bad priority', '', Ticket::TYPE_TASK, 'urgent', null, null, null, [], null, null);
    }

    public function testReopenClosedTicket(): void
    {
        $pdo = $this->createDatabase();
        $service = new TicketService($pdo);

        $ticket = $service->create(
            'ns', 'Reopen test', '', Ticket::TYPE_TASK,
            Ticket::PRIORITY_NORMAL, null, null, null, [], null, null
        );
        $ticket = $service->transition($ticket->getId(), Ticket::STATUS_IN_PROGRESS);
        $ticket = $service->transition($ticket->getId(), Ticket::STATUS_RESOLVED);
        $ticket = $service->transition($ticket->getId(), Ticket::STATUS_CLOSED);

        $reopened = $service->transition($ticket->getId(), Ticket::STATUS_OPEN);
        $this->assertSame(Ticket::STATUS_OPEN, $reopened->getStatus());
    }

    private function createDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            namespace TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "open",
            priority TEXT NOT NULL DEFAULT "normal",
            type TEXT NOT NULL DEFAULT "task",
            reference_type TEXT NULL,
            reference_id INTEGER NULL,
            assignee TEXT NULL,
            labels TEXT NOT NULL DEFAULT "[]",
            due_date TEXT NULL,
            created_by TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE ticket_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
            author TEXT NOT NULL,
            body TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        return $pdo;
    }
}
