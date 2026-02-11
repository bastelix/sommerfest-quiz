# Changelog

## [unreleased]

### Build

- Tag image as sommerfest-quiz

### Chore

- Add pages table for SEO config
- Increase phpstan memory limit
- Restore config
- Scope landing page styles
- Apply dark blue landing theme
- Improve landing page contrast
- Refine compose project handling
- *(deps)* Bump symfony/process from 7.3.0 to 7.3.3
- *(deps)* Bump symfony/mailer from 7.3.2 to 7.3.3
- *(deps)* Bump stripe/stripe-php from 17.5.0 to 17.6.0
- *(deps)* Bump slim/slim from 4.14.0 to 4.15.0
- *(deps)* Bump guzzlehttp/guzzle from 7.9.3 to 7.10.0
- Normalize invite setup indentation
- Restore config backup
- *(docs)* Remove unused jekyll rtd theme
- Add migration for logo width
- Mark admin script as module
- Streamline catalog styles
- Preserve query string on rewrite
- Remove obsolete comment
- Send JSON accept header when fetching catalogs
- Seed admin user
- Handle network errors in event switcher
- Add migration for sticker text toggles
- Normalize spacing before EOF docblocks
- Track namespace-specific token CSS and tighten .gitignore
- Remove legacy labor.css, topbar.landing.css, and dead references

### Ci

- Rebase changelog updates before push
- Add GitHub Pages workflow

### Docs

- Remove version bump entries from changelog
- Note removal of docker tag for tenant upgrade
- Note landing page style overrides
- Clarify tenant wait probing and HTTPS upgrade
- Document Docker Compose project name
- Dokumentation des table frameworks
- Update table framework guide
- Add local documentation build instructions
- Escape twig example in docs
- Describe QR code endpoint requirements
- Document active event handling
- Add domain configuration section
- Clarify quiz start comment
- Note management role redirect
- Refresh contribution and coding guides
- Explain team name ai preview
- Document marketing menu assignments
- Add comprehensive architecture review against AGENTS.md
- Add prioritized architecture task list (27 tasks in 7 phases)

### Feat

- Allow selecting subscription plan
- Persist and extend QR design
- Allow resending welcome mail
- *(admin)* Allow column selection for tenants
- Show tenant invoices
- Enable subscription plan toggle for all tenants
- Add health endpoint and improve onboarding check
- Add automatic patch bump workflow
- Enforce password repeat in reset
- Group admin options by category
- Extend dark mode styling to html element
- Sync Stripe subscription on plan change
- Increase icon button tap size
- Enable automatic dark mode
- Make font sizes responsive
- Always show tenant actions in dropdown
- Add dynamic canonical and OG URLs
- Add theme color variables
- Add dynamic nav placeholder sizing
- Add responsive offcanvas menu toggle
- Add mobile offcanvas navigation
- Validate redirects before navigation
- Allow hiding topbar icons
- Add compact settings dropdown
- Add player profile page
- Add profile name management
- Enforce profile setup for random names
- Store player names per event
- Add exponential backoff for db connection
- Improve topbar accessibility
- Pull image before tenant upgrade
- Allow custom image tag for tenant upgrade
- Consolidate admin toolbar actions
- Improve navigation accessibility
- Dark pricing cards
- Toggle dark stylesheet via disabled attribute
- Themeable landing topbar
- Improve landing page theme variables
- Streamline dark mode handling
- Improve dark theme colors
- Use Poppins font for body
- Replace landing page with new design
- Apply design system to landing
- Refresh landing page
- *(dark-mode)* Refine login input styling
- Initialize theme before css load
- Mobil-first landing styles
- Darken login version tag
- Add redirects table
- Apply dark mode classes without stylesheet
- Simplify landing hero
- Use uikit navbar toggle
- Add offcanvas nav to marketing landing
- Add light landing theme with dark override
- Redesign landing topbar
- Implement theme toggle and qr tokens for landing
- Improve tenant creation error handling
- Set default light theme
- Add icons to landing page navigation
- Add collapsible log to onboarding
- Improve tenant health check and TLS logging
- Require Stripe pricing table
- Toggle dark mode class
- *(editor)* Add section template after hero
- Enable dark mode by default on landing
- Load .env variables in onboarding script
- Limit onboarding task log height
- Add language menu to landing
- Update how-it-works section
- Ensure btn icons match text color
- Add use case slider
- Expand landing FAQ
- Update contact block copy
- Greet returning team name
- Preserve catalog questions on slug change
- *(admin)* Edit teams via modal
- Add qr action button style
- Add teams speed dial
- Add pagination for team management
- Add light mode row styling
- Refine QR code defaults
- Add swipe card styles
- Add table macros for admin views
- Add generic table manager
- Add mobile card rendering with accessibility
- Polish table layout and theme variables
- Add table macros and update docs
- Add table manager utility
- Add twig template for tenant rows
- Update catalog manager save logic
- *(admin)* Render catalog mobile cards
- Expose event config endpoints
- Implement event config page logic
- *(admin)* Enhance event config sidebar
- Add pagination and classes to catalog manager
- Guard table manager init
- Add mobile action menu and modal editing for catalogs
- Improve mobile table layout
- Add paginated catalog endpoint
- Add accessible table loading spinners
- Allow custom QR code options
- Add mobile labels for table columns
- Apply default QR code layout
- Add explicit chillerlan qr options
- Use table layout for admin events
- Make QR logo punch-out configurable
- Punchout logo area in svg QR codes
- Add reusable cell editor helper
- Support event-specific QR logo endpoints
- *(admin)* Add catalog edit modal
- Auto-save catalog changes
- *(admin)* Add missing events table
- Autosave questions
- Add event-specific config endpoint
- Add eventless catalog fallback
- Add save button identifier
- Add autosave for loaded config
- Make tenants directory configurable
- Enable autosave for event configuration
- Import existing page content
- Add catalog load warning
- *(catalog)* Show intro on initial load
- Resolve catalog slug from URL path
- Add async catalog selection
- Centralize storage utilities
- Add player session endpoint
- *(storage)* Scope puzzle and catalog data
- Provide storage fallbacks for catalog
- Redirect event selection via query
- Show event name in admin headings
- Pass event uid to result service
- Collect solved catalogs in competition mode
- Preload events on admin page
- Remove events pagination limit
- Warn if no events present
- Combine team actions into single column
- Move team name prompt to top bar
- Globalize player name storage
- Allow resetting stored team name
- Show team name button
- Stop resuming catalog from session
- Enhance event catalogs layout
- Adapt onboarding buttons for dark mode
- Show event header outside topbar
- Improve sticker background upload UX
- Improve sticker background upload UX
- Add catalog sticker preview
- Add dark-mode contrast for secondary buttons
- Suggest username for manual QR input
- Enable dark theme on license page
- Update user rendering
- Highlight catalog cards in dark mode
- Prevent replaying solved catalogs
- Add label for event selection
- Use native select for event picker
- Inline role editing
- Add username modal for admin
- Dynamically switch admin events
- Hide event selector on small screens
- Persist letter in puzzle mode
- Prevent admin event change auto reload
- Widen sticker editor modal
- Reintroduce additional Avery sticker templates
- Introduce image upload service
- Centralize event image storage
- Auto orient image uploads
- Add image format option
- Support per-line font sizes in sticker preview
- Add catalog fallback
- Use new image upload controller for sticker background
- Use UIkit uploader for sticker backgrounds
- Preserve text box position during resize
- *(sticker)* Support padding in editor
- Add mm grid snapping for sticker editor
- Refine sticker QR size field
- Allow marketing contact forms to define endpoint
- Add animated calserver hero background
- Implement future-is-green mega menu
- Add domain-specific RAG management
- Manage RAG chat settings in admin
- Expand calHelp case stories
- Redesign calhelp news section
- Redesign calhelp news section
- Add CalHelp section theming
- Allow customizing live dashboard widget titles
- Allow marketing event pages to adopt event branding
- Integrate events module into namespace system
- Add advanced footer block system with content types
- Add automatic WCAG color contrast checking for theme tokens
- Add conventional commits and manual version bump support
- Make all page module block types mobile-first responsive
- Add responsive font-size scaling for all heading classes in modules

### Fix

- Update phpstan config for v2
- Ensure page content respects dynamic topbar height
- *(events)* Prevent table frame from clipping actions
- Respect flex-wrap when sizing nav placeholder
- Remove empty hamburger menu and align settings toggle
- Always show topbar icons
- Handle missing player name key
- Align config button to navbar edge
- Remove opacity from accessibility toggle icon
- Ensure landing navbar uses UIKit-specific background
- Improve landing page contrast
- Restore backend light mode styles
- Make landing topbar github buttons rectangular
- Serve assets with base path
- Reveal hero gradient
- Allow landing offcanvas to overlay viewport
- Improve topbar contrast in light theme
- Override UIkit card backgrounds
- Remove redundant nullsafe operator
- Align landing button icon color
- Preserve player name across catalogs
- Ensure consistent table cell padding
- Preserve svg logo aspect ratio
- Reuse catalog uid for existing slug
- Avoid autosave after load
- Reuse existing catalog uid for duplicate slug
- Address phpstan warnings
- Remove admin topbar position override
- Avoid duplicate params declaration
- Align admin action wrappers to right
- Preserve player name on restart
- Restore landing theme toggles
- Load storage helper for options menu
- Scope topbar button bg for landing
- Apply topbar button background on landing
- Dark mode landing primary buttons
- Verify sticker background path
- Remove quoted identifiers in sticker migration
- Allow automated commits to trigger ci
- Guard entrypoint pipefail usage
- Make admin.js event handling namespace-aware
- Resolve PHPStan errors in LegacyCalserverLandingController
- Enforce namespace isolation across all event-related endpoints
- Enforce namespace access checks on admin content write operations
- Link brand tokens to marketing vars when scheme is active in namespace-design
- Resolve admin page tree loading and namespace design application
- Restore missing pageTypeDefaultsList variable in content.twig
- Use DOMContentLoaded for page tree init and harden apiFetch fallback
- Escape Liquid/Twig syntax in docs to fix GitHub Pages build
- Add missing section CSS for namespace-themed block rendering
- Add missing CSS tokens and block styles for CMS page rendering
- Connect theme blocks to contrast system, improve palettes
- Reset text/link color inside cards on coloured section backgrounds
- Resolve GitHub Actions race condition with concurrency groups
- Remove margin-top between sections that caused unfilled gaps
- Hero text contrast on colored sections + eyebrow tag option
- FAQ accordion contrast — inherit section text color, adaptive icons
- Align block renderer output with UIkit HTML reference styling
- Resolve missing stylesheet contrast on marketing landing page
- Regenerate namespace token CSS and load topbar styles on CMS pages
- Harden section contrast for dark backgrounds and extract renderSectionHeader helper

### Refactor

- Centralize SMTP config loading
- Wrap invoice return docblock
- Sanitize subscription DOM updates
- Build pagination DOM and escape paths
- Externalize onboarding styles
- Use accent color variable in dark mode
- Use CSS variables for landing page
- Update topbar text colors
- Unify landing page theme variables
- Scope dark mode styles
- Replace landing page inline styles
- Improve dark mode handling
- Apply accessibility landing style patch
- Extract landing tokens into scss
- Consolidate landing page design tokens
- Centralize landing navigation links
- Use data-theme for dark mode
- Add log details container
- Replace UIkit card modifiers with qr-card
- Add theme-aware icon color
- Derive base url from request
- Add qr color variables
- Extract table styles and theme variables
- Move configuration fully to database
- Filter tenants by status safely
- Use table macro in admin
- Wrap long lines in QrCodeService
- Streamline catalog and team tables
- Use catalogKey for identifiers
- Remove redundant null coalesce
- Centralize quiz intro
- Rely on SessionMiddleware
- Centralize session post requests
- Rename active event uid variable
- Remove config fallback for empty event
- Merge password action into user actions
- Centralize event switching
- Add manual drag fallback
- Replace sticker background upload
- Add stage container for sticker editor
- Centralize calhelp placeholder injection
- Relocate admin event controls
- Phase 0-2 – fix AGENTS.md, remove require_once, add namespace scoping
- Phase 3/5/7 – extract middleware, shared JS utils, establish ADRs
- Improve admin pages UI for clarity and reduced clutter

### Style

- Add top spacing to event selector label
- Match hamburger menu toggle to options button
- Improve landing topbar contrast
- Square icon buttons
- Add dark mode styles for landing page
- Align topbar link colors with theme
- Use variables for footer colors
- Refine landing visuals
- Improve form error contrast
- Darken options button in light mode
- Improve landing backgrounds for themes
- Add danger color tokens
- Emulate GitHub header on landing
- Update landing hero styling
- Add git style to menu toggle
- Switch landing page to light theme
- Refine landing focus and hover styles
- Expand scenario pill list
- Unify landing footer layout
- Use black text for contact details on light theme
- Enhance landing slider
- Make event start button text white
- Adjust team table layout
- Add admin config CSS with dark mode variables
- Remove extra blank line
- Add draggable sticker handle
- Refresh calserver card styling

### Test

- Enforce starter plan event limit
- Add plan limit controller tests
- Switch to postgres in tests
- Support sqlite memory db
- Verify health endpoint timestamp and tenant host
- Ensure version displayed on login
- Cover catalog question access
- Verify catalog slug parameter
- Validate catalog slug selection
- Verify slug option selection
- Add handleSelection header test
- Cover quiz autostart error cases
- Persist session on localhost
- Ensure login redirect on main domain
- Cover team name suggestion
- Cover user position ordering
- Ensure managers redirect to admin
- Ensure summary updates after event switch
- Generate sticker background during test
- Use new sticker background route

## [0.2.0] - 2025-08-18

### Dokumentation

- Statistik-Tab beschrieben

### Chore

- Cascade delete events
- Pin GitHub Actions to SHA
- Apply unless-stopped restart policy
- *(test)* Run migrations for sqlite
- *(test)* Remove extra newline
- Split ranking regex lines
- Upgrade QR code library
- Optimize landing page images
- Add ghostscript to Docker image
- Require PHP 8.2
- Test against php 8.2
- Update dependencies
- Pin php base images to patch release
- Add migration for Stripe customer id
- Restrict access to hidden files
- Use textContent for quiz buttons
- Fix style and security warnings
- Reformat stripe tests for readability
- Release 0.2.0

### Ci

- Add workflow for automatic changelog
- Build tenant image during deploy
- Use setup-php v2
- Run deploy on push to main

### Docs

- Add postgres instructions
- Document running psql via Docker
- Add frontend word-break guidelines
- Switch to MkDocs readthedocs
- Add hierarchical navigation
- Add version history
- Mention exif extension
- Add tenant API section
- Add tenant features section
- Clarify event table columns
- Fix shell commands for alpine
- Ignore changelog update commits
- Add troubleshooting for test data
- Update docker setup instructions
- Clarify php.ini usage
- Add domain workflow and signup instructions
- Add nginx onboarding note
- Add migration step
- Clarify DB privileges for tenants
- Mention onboard_tenant script
- Clarify Docker requirement for onboarding
- Mention proxy network
- Clarify APP_IMAGE usage
- Describe password reset flow
- Describe higher subscription tiers cumulatively
- Document PHP 8.2 requirement
- Add PHP 8.2 stable support note
- Document qr code enhancements
- Outline SEO and SEF requirements
- Note removal of unsupported automatic payment methods
- Note removal of unsupported automatic payment methods

### Feat

- Skip schema init when db ready
- Support soft hyphen placeholders
- Add dynamic congratulations for team PDFs
- Add event API and schema columns
- *(admin)* Activate event selection
- Confirm event deletion
- Centralize roles and update management
- Seed users for all roles
- Improve mobile admin nav
- *(summary)* Link catalog headers
- *(migrations)* Add QRRemember manual migration
- *(db)* Use consolidated base schema
- Seed default roles via migration
- *(migrations)* Add admin seed
- Allow restore and delete of old backups
- *(admin)* Add dropdown for active event
- *(admin)* Landing page option
- *(admin)* Improve mobile event list
- Add admin page editor
- Integrate static page editing
- *(admin)* Allow editing of FAQ page
- Retry database connection
- *(admin)* Add language selector
- Manage nginx vhost creation
- *(onboarding)* Create admin account and show password
- Add tenant availability check
- Add nginx reload route
- Support webhook-based nginx reload
- Display tenants tab for admin
- Launch tenant webserver for ssl
- Add SSL renewal for tenants
- Auto onboard when credentials available
- *(admin)* Link tenant subdomains
- *(onboarding)* Show link to new container
- Collapse onboarding logs and track availability
- *(admin)* Add frontend open button for selected event
- *(admin)* Show tenant profile on main domain
- Add blacklist for sensitive subdomains
- Support profile variables in pages
- *(admin)* Autosave settings
- Style landing page with calhelp design
- Autosave for event configuration
- Add save text button for event settings
- Store admin password per tenant
- Collect and store user email
- Add password reset endpoints
- Add password reset templates
- Allow publishing events and copying links
- Add fallbacks for QR and logo
- Center landing page topbar elements
- Make landing footer non-sticky
- Animate landing page
- Animate landing page content blocks
- Add horizontal scrollspy animations
- Enhance landing page messaging
- Align subscription templates with new plan details
- Add subscription matrix to landing page
- Add event selector to summary and results
- Add invitation handling
- Secure password reset tokens
- Implement QR code service
- Update admin QR codes to new design
- Add parameterized QR routes
- Elevate Warum QuizRace section
- Add rotating word animation to hero
- Add logo and rounding to QR codes
- Enhance rotating word marker
- Style founder section
- *(stripe)* Add session status endpoint
- Add reusable admin navigation partial
- Group admin navigation links
- Centralize SMTP sender configuration
- Enforce stripe-only payments
- Add page SEO config
- *(admin)* Move stats filter below header on mobile
- Fallback to null logger when monolog missing
- Add subscription usage widget
- Support embedded stripe checkout
- Show event summary on dashboard
- Add csrf protection to profile page
- *(admin)* Greet user on dashboard
- *(onboarding)* Show upcoming steps
- Validate onboarding inputs
- *(onboarding)* Reinstate app creation progress
- Enhance tenant onboarding tasks
- Fallback logo path when event id missing
- Enhance tenant table UI
- Add demo data export
- Make database connection retries configurable
- Add customizable qr design modal
- Add responsive email layout
- Support configurable QR designs
- Show overall event counts on dashboard

### Fix

- *(migrate)* Clear reference after uid update
- *(qr)* Enable camera flip after scanner ready
- *(qr)* Await scanner init
- Avoid scanner timing race
- Hide catalog list when repeating in competition mode
- *(js)* Refresh UIkit lightboxes
- Sanitize migrations
- Avoid using first event if active one missing
- Address style warnings
- Encode flip card text
- Add summary photo service dependency
- Support PostgreSQL serial syntax
- Standardize qrremember column name
- *(migrations)* Ensure idempotent sort order constraint
- Align config controller test with default settings
- Format event dates for datetime-local
- Ensure topbar icons work on mobile
- Respect base path in admin JS
- Serve event-specific logo paths
- Remove landing menu in admin
- *(onboard)* Write error messages to stderr
- Detect docker compose command
- Skip docker compose check when reload disabled
- *(admin)* Ensure ssl renewal starts tenant
- Ensure landing route sets domain type
- Apply dark mode styles to page editor
- Read SMTP settings from .env
- Ensure logo uploads work across image versions
- Update QR code generation
- Enforce fixed topbar height
- Prevent landing page header overlap on mobile
- Render footer menu at page bottom
- Import email confirmation service
- Show feedback after password reset request
- Address code style issues
- Ensure mobile nav active items remain readable
- Improve active nav contrast in dark menu
- Add migration for password reset token hash
- Remove redundant null coalesce
- Improve dark mode menu active state
- Handle missing onboarding data after payment
- Avoid published column migration error
- Remove fallback logo srcset
- Show catalog info on start page
- Remove manual chunked encoding in tenant onboarding
- Avoid global variable conflict on landing page
- Align QR config migration with camelCase columns

### Refactor

- Wrap long SQL strings
- Reuse existing events script for event selector
- Use constants for qr code defaults
- Redesign dashboard calendar
- Avoid multiple classes in test
- Drop qrcode url field

### Style

- Add custom file input
- Enhance catalog comment card
- Enhance rotate button appearance
- Improve mobile layout for events
- Unify vue quiz buttons
- Unify admin question buttons
- Wrap long SQL strings in TenantService
- Improve landing page layout
- Enlarge hero heading and center button text
- Harmonize landing page section backgrounds
- Use neutral background for founder section
- Add textured underline animation
- Remove Weisheit font from about section
- Modernize onboarding step boxes
- Show pricing plans in onboarding
- Improve event selector display
- Remove trailing blank line

### Test

- Switch service tests to use sqlite
- Verify tenant creation and removal
- Fix schemas for event-aware tables
- Verify catalog create and delete via db
- Expect marketing pages on main domain
- Cover main profile update
- *(admin)* Ensure dashboard greets username
- *(admin)* Ensure dashboard greets username
- Update profile plan enum
- Cover award ranking edge cases
- Cover welcome mail resend

## [0.1.0] - 2025-06-16

### Chore

- Update php requirements

### Doc

- Elaborate license section
- Explain manual composer workflow

### Docs

- Add info boxes for manual file replacement
- Add html validity badge
- Expand team tab help
- Add deployment status badge
- Remove outdated statistical.log references
- Expand accessibility notes
- Update README with development focus and config
- Mention composer lock update

### Feat

- Show results overview and restart option
- Add catalog selection
- Show QR login on start
- Auto-open QR scan for direct catalog links
- Use SortableJS for drag operations
- *(admin)* Integrate results tab
- *(config)* Migrate legacy config
- *(admin)* Auto-generate catalog ids
- *(data)* Add remote QR codes for catalog samples
- *(data)* Add remote QR codes for catalog samples
- *(pdf)* Add login qr code
- *(admin)* Auto-generate unique catalog ids
- *(export)* Show records as printable cards
- Allow logo upload
- *(photos)* Restore consent modal

### Fix

- Remove duplicate feedback vars
- Show catalog selection with config header and move questions to json
- Keep topbar at page top
- *(pdf)* Show QR code column when qrcode_url present
- *(catalog)* Ensure catalog files persist

### Style

- Align remove button right
- Enlarge drag elements
- Enlarge multiple choice view
- Apply dark mode to qr modal
- Add top spacing above header
- *(login)* Center login card
- Enhance question preview
- Ensure card titles are white in dark mode
- *(results)* Use uk-leader layout for top rankings

