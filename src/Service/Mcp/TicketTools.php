<?php

declare(strict_types=1);

namespace App\Service\Mcp;

use App\Domain\Ticket;
use App\Service\TicketService;
use PDO;

final class TicketTools
{
    private TicketService $ticketService;

    private const NS_PROP = [
        'type' => 'string',
        'description' => 'Optional namespace (defaults to the token namespace)',
    ];

    public function __construct(PDO $pdo, private readonly string $defaultNamespace)
    {
        $this->ticketService = new TicketService($pdo);
    }

    private function resolveNamespace(array $args): string
    {
        $ns = isset($args['namespace']) && is_string($args['namespace']) ? trim($args['namespace']) : '';
        return $ns !== '' ? $ns : $this->defaultNamespace;
    }

    /**
     * @return list<array{name: string, method: string, description: string, inputSchema: array}>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'list_tickets',
                'method' => 'listTickets',
                'description' => 'List tickets for a namespace. Optionally filter '
                    . 'by status, priority, type, assignee, or reference.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'status' => [
                            'type' => 'string',
                            'description' => 'Filter by status',
                            'enum' => ['open', 'in_progress', 'resolved', 'closed'],
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Filter by priority',
                            'enum' => ['low', 'normal', 'high', 'critical'],
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Filter by type',
                            'enum' => ['bug', 'task', 'review', 'improvement'],
                        ],
                        'assignee' => ['type' => 'string', 'description' => 'Filter by assignee username'],
                        'referenceType' => [
                            'type' => 'string',
                            'description' => 'Filter by reference type',
                            'enum' => ['wiki_article', 'page'],
                        ],
                        'referenceId' => ['type' => 'integer', 'description' => 'Filter by reference ID'],
                    ],
                ],
            ],
            [
                'name' => 'get_ticket',
                'method' => 'getTicket',
                'description' => 'Get a single ticket by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Ticket ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'create_ticket',
                'method' => 'createTicket',
                'description' => 'Create a new ticket for QA, project management, or bug tracking.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'title' => ['type' => 'string', 'description' => 'Ticket title'],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Ticket description (supports markdown)',
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Ticket type',
                            'enum' => ['bug', 'task', 'review', 'improvement'],
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priority level',
                            'enum' => ['low', 'normal', 'high', 'critical'],
                        ],
                        'referenceType' => [
                            'type' => 'string',
                            'description' => 'Type of linked entity',
                            'enum' => ['wiki_article', 'page'],
                        ],
                        'referenceId' => ['type' => 'integer', 'description' => 'ID of the linked entity'],
                        'assignee' => ['type' => 'string', 'description' => 'Username to assign the ticket to'],
                        'labels' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'List of labels/tags',
                        ],
                        'dueDate' => ['type' => 'string', 'description' => 'Due date in ISO 8601 format'],
                        'createdBy' => ['type' => 'string', 'description' => 'Username of the ticket creator'],
                    ],
                    'required' => ['title'],
                ],
            ],
            [
                'name' => 'update_ticket',
                'method' => 'updateTicket',
                'description' => 'Update an existing ticket. Only provided fields are changed.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Ticket ID'],
                        'title' => ['type' => 'string', 'description' => 'New title'],
                        'description' => ['type' => 'string', 'description' => 'New description'],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'New priority',
                            'enum' => ['low', 'normal', 'high', 'critical'],
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'New type',
                            'enum' => ['bug', 'task', 'review', 'improvement'],
                        ],
                        'assignee' => [
                            'type' => 'string',
                            'description' => 'New assignee username (or null to unassign)',
                        ],
                        'labels' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'New labels',
                        ],
                        'dueDate' => ['type' => 'string', 'description' => 'New due date (or null to remove)'],
                        'referenceType' => [
                            'type' => 'string',
                            'description' => 'Reference type',
                            'enum' => ['wiki_article', 'page'],
                        ],
                        'referenceId' => ['type' => 'integer', 'description' => 'Reference ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'transition_ticket',
                'method' => 'transitionTicket',
                'description' => 'Change ticket status. Allowed: '
                    . 'open->in_progress|closed, in_progress->resolved|open, '
                    . 'resolved->closed|in_progress, closed->open.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Ticket ID'],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Target status',
                            'enum' => ['open', 'in_progress', 'resolved', 'closed'],
                        ],
                    ],
                    'required' => ['id', 'status'],
                ],
            ],
            [
                'name' => 'delete_ticket',
                'method' => 'deleteTicket',
                'description' => 'Delete a ticket by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'id' => ['type' => 'integer', 'description' => 'Ticket ID'],
                    ],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'list_ticket_comments',
                'method' => 'listTicketComments',
                'description' => 'List all comments for a ticket.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'ticketId' => ['type' => 'integer', 'description' => 'Ticket ID'],
                    ],
                    'required' => ['ticketId'],
                ],
            ],
            [
                'name' => 'add_ticket_comment',
                'method' => 'addTicketComment',
                'description' => 'Add a comment to a ticket.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'ticketId' => ['type' => 'integer', 'description' => 'Ticket ID'],
                        'author' => ['type' => 'string', 'description' => 'Comment author username'],
                        'body' => ['type' => 'string', 'description' => 'Comment body (supports markdown)'],
                    ],
                    'required' => ['ticketId', 'author', 'body'],
                ],
            ],
            [
                'name' => 'delete_ticket_comment',
                'method' => 'deleteTicketComment',
                'description' => 'Delete a ticket comment by ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'namespace' => self::NS_PROP,
                        'commentId' => ['type' => 'integer', 'description' => 'Comment ID'],
                    ],
                    'required' => ['commentId'],
                ],
            ],
        ];
    }

    public function listTickets(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $filters = [];
        foreach (['status', 'priority', 'type', 'assignee', 'referenceType', 'referenceId'] as $key) {
            if (isset($args[$key]) && $args[$key] !== '') {
                $filters[$key] = $args[$key];
            }
        }

        $tickets = $this->ticketService->listByNamespace($ns, $filters);

        return [
            'namespace' => $ns,
            'tickets' => array_map(static fn (Ticket $t): array => $t->jsonSerialize(), $tickets),
        ];
    }

    public function getTicket(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $ticket = $this->ticketService->getById($id);
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found');
        }

        return [
            'namespace' => $this->resolveNamespace($args),
            'ticket' => $ticket->jsonSerialize(),
        ];
    }

    public function createTicket(array $args): array
    {
        $ns = $this->resolveNamespace($args);
        $title = isset($args['title']) && is_string($args['title']) ? $args['title'] : '';
        $description = isset($args['description']) && is_string($args['description']) ? $args['description'] : '';
        $type = isset($args['type']) && is_string($args['type']) ? $args['type'] : Ticket::TYPE_TASK;
        $priority = isset($args['priority']) && is_string($args['priority']) ? $args['priority'] : Ticket::PRIORITY_NORMAL;
        $referenceType = isset($args['referenceType']) && is_string($args['referenceType'])
            ? $args['referenceType']
            : null;
        $referenceId = isset($args['referenceId']) ? (int) $args['referenceId'] : null;
        $assignee = isset($args['assignee']) && is_string($args['assignee']) ? $args['assignee'] : null;
        $labels = isset($args['labels']) && is_array($args['labels']) ? $args['labels'] : [];
        $dueDate = isset($args['dueDate']) && is_string($args['dueDate']) ? $args['dueDate'] : null;
        $createdBy = isset($args['createdBy']) && is_string($args['createdBy']) ? $args['createdBy'] : null;

        if ($referenceId === 0) {
            $referenceId = null;
        }

        $ticket = $this->ticketService->create(
            $ns,
            $title,
            $description,
            $type,
            $priority,
            $referenceType,
            $referenceId,
            $assignee,
            $labels,
            $dueDate,
            $createdBy
        );

        return [
            'namespace' => $ns,
            'ticket' => $ticket->jsonSerialize(),
        ];
    }

    public function updateTicket(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $fields = [];
        $updatableKeys = [
            'title', 'description', 'priority', 'type',
            'assignee', 'labels', 'dueDate', 'referenceType', 'referenceId',
        ];
        foreach ($updatableKeys as $key) {
            if (array_key_exists($key, $args)) {
                $fields[$key] = $args[$key];
            }
        }

        $ticket = $this->ticketService->update($id, $fields);

        return [
            'namespace' => $this->resolveNamespace($args),
            'ticket' => $ticket->jsonSerialize(),
        ];
    }

    public function transitionTicket(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $status = isset($args['status']) && is_string($args['status']) ? $args['status'] : '';
        if ($status === '') {
            throw new \InvalidArgumentException('status is required');
        }

        $ticket = $this->ticketService->transition($id, $status);

        return [
            'namespace' => $this->resolveNamespace($args),
            'ticket' => $ticket->jsonSerialize(),
        ];
    }

    public function deleteTicket(array $args): array
    {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id <= 0) {
            throw new \InvalidArgumentException('id is required');
        }

        $this->ticketService->delete($id);

        return ['status' => 'deleted', 'id' => $id];
    }

    public function listTicketComments(array $args): array
    {
        $ticketId = isset($args['ticketId']) ? (int) $args['ticketId'] : 0;
        if ($ticketId <= 0) {
            throw new \InvalidArgumentException('ticketId is required');
        }

        $comments = $this->ticketService->listComments($ticketId);

        return [
            'namespace' => $this->resolveNamespace($args),
            'ticketId' => $ticketId,
            'comments' => array_map(static fn ($c): array => $c->jsonSerialize(), $comments),
        ];
    }

    public function addTicketComment(array $args): array
    {
        $ticketId = isset($args['ticketId']) ? (int) $args['ticketId'] : 0;
        if ($ticketId <= 0) {
            throw new \InvalidArgumentException('ticketId is required');
        }

        $author = isset($args['author']) && is_string($args['author']) ? $args['author'] : '';
        $body = isset($args['body']) && is_string($args['body']) ? $args['body'] : '';

        $comment = $this->ticketService->addComment($ticketId, $author, $body);

        return [
            'namespace' => $this->resolveNamespace($args),
            'comment' => $comment->jsonSerialize(),
        ];
    }

    public function deleteTicketComment(array $args): array
    {
        $commentId = isset($args['commentId']) ? (int) $args['commentId'] : 0;
        if ($commentId <= 0) {
            throw new \InvalidArgumentException('commentId is required');
        }

        $this->ticketService->deleteComment($commentId);

        return ['status' => 'deleted', 'commentId' => $commentId];
    }
}
