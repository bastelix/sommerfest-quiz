<?php

declare(strict_types=1);

namespace App\Domain;

final class Roles
{
    public const ADMIN = 'admin';
    public const DESIGNER = 'designer';
    public const REDAKTEUR = 'redakteur';
    public const CATALOG_EDITOR = 'catalog-editor';
    public const EVENT_MANAGER = 'event-manager';
    public const ANALYST = 'analyst';
    public const TEAM_MANAGER = 'team-manager';
    public const SERVICE_ACCOUNT = 'service-account';

    public const ALL = [
        self::ADMIN,
        self::DESIGNER,
        self::REDAKTEUR,
        self::CATALOG_EDITOR,
        self::EVENT_MANAGER,
        self::ANALYST,
        self::TEAM_MANAGER,
        self::SERVICE_ACCOUNT,
    ];

    /**
     * Roles that have access to the interactive admin UI.
     *
     * Service accounts are excluded because they are meant for
     * automation and do not have a dashboard.
     */
    public const ADMIN_UI = [
        self::ADMIN,
        self::CATALOG_EDITOR,
        self::EVENT_MANAGER,
        self::ANALYST,
        self::TEAM_MANAGER,
    ];
}
