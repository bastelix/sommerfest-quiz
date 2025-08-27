# Changelog

## [unreleased]

### Build

- Tag image as sommerfest-quiz

### Chore

- Add pages table for SEO config
- Increase phpstan memory limit
- Bump version to 0.2.1 [skip ci]
- Bump version to 0.2.2 [skip ci]
- Bump version to 0.2.3 [skip ci]
- Bump version to 0.2.4 [skip ci]
- Bump version to 0.2.5 [skip ci]
- Bump version to 0.2.6 [skip ci]
- Bump version to 0.2.7 [skip ci]
- Bump version to 0.2.8 [skip ci]
- Bump version to 0.2.9 [skip ci]
- Bump version to 0.2.10 [skip ci]
- Bump version to 0.2.11 [skip ci]
- Bump version to 0.2.12 [skip ci]
- Bump version to 0.2.13 [skip ci]
- Bump version to 0.2.14 [skip ci]
- Bump version to 0.2.15 [skip ci]
- Bump version to 0.2.16 [skip ci]
- Bump version to 0.2.17 [skip ci]
- Bump version to 0.2.18 [skip ci]
- Bump version to 0.2.19 [skip ci]
- Bump version to 0.2.20 [skip ci]
- Bump version to 0.2.21 [skip ci]
- Bump version to 0.2.22 [skip ci]
- Bump version to 0.2.23 [skip ci]
- Bump version to 0.2.24 [skip ci]
- Bump version to 0.2.25 [skip ci]
- Bump version to 0.2.26 [skip ci]
- Bump version to 0.2.27 [skip ci]
- Bump version to 0.2.28 [skip ci]
- Bump version to 0.2.29 [skip ci]
- Bump version to 0.2.30 [skip ci]
- Bump version to 0.2.31 [skip ci]
- Bump version to 0.2.32 [skip ci]
- Bump version to 0.2.33 [skip ci]
- Bump version to 0.2.34 [skip ci]
- Bump version to 0.2.35 [skip ci]
- Bump version to 0.2.36 [skip ci]
- Bump version to 0.2.37 [skip ci]
- Bump version to 0.2.38 [skip ci]
- Bump version to 0.2.39 [skip ci]
- Restore config
- Bump version to 0.2.40 [skip ci]
- Bump version to 0.2.41 [skip ci]
- Bump version to 0.2.42 [skip ci]
- Bump version to 0.2.43 [skip ci]
- Bump version to 0.2.44 [skip ci]
- Bump version to 0.2.45 [skip ci]
- Bump version to 0.2.46 [skip ci]
- Bump version to 0.2.47 [skip ci]
- Bump version to 0.2.48 [skip ci]
- Bump version to 0.2.49 [skip ci]
- Bump version to 0.2.50 [skip ci]
- Bump version to 0.2.51 [skip ci]
- Bump version to 0.2.52 [skip ci]
- Bump version to 0.2.53 [skip ci]
- Bump version to 0.2.54 [skip ci]
- Bump version to 0.2.55 [skip ci]
- Bump version to 0.2.56 [skip ci]
- Bump version to 0.2.57 [skip ci]
- Bump version to 0.2.58 [skip ci]
- Bump version to 0.2.59 [skip ci]
- Bump version to 0.2.60 [skip ci]
- Bump version to 0.2.61 [skip ci]
- Bump version to 0.2.62 [skip ci]
- Bump version to 0.2.63 [skip ci]
- Bump version to 0.2.64 [skip ci]
- Bump version to 0.2.65 [skip ci]
- Bump version to 0.2.66 [skip ci]
- Bump version to 0.2.67 [skip ci]
- Bump version to 0.2.68 [skip ci]
- Bump version to 0.2.69 [skip ci]
- Bump version to 0.2.70 [skip ci]
- Bump version to 0.2.71 [skip ci]
- Bump version to 0.2.72 [skip ci]
- Bump version to 0.2.73 [skip ci]
- Bump version to 0.2.74 [skip ci]
- Bump version to 0.2.75 [skip ci]
- Bump version to 0.2.76 [skip ci]

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

### Fix

- Update phpstan config for v2
- Ensure page content respects dynamic topbar height
- *(events)* Prevent table frame from clipping actions
- Respect flex-wrap when sizing nav placeholder

### Refactor

- Centralize SMTP config loading
- Wrap invoice return docblock
- Sanitize subscription DOM updates
- Build pagination DOM and escape paths

### Style

- Add top spacing to event selector label

### Test

- Enforce starter plan event limit
- Add plan limit controller tests
- Switch to postgres in tests
- Support sqlite memory db
- Verify health endpoint timestamp and tenant host
- Ensure version displayed on login

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

