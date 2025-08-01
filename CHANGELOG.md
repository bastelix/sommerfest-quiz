# Changelog

## [unreleased]

### Dokumentation

- Statistik-Tab beschrieben

### Chore

- Cascade delete events
- Pin GitHub Actions to SHA
- Apply unless-stopped restart policy
- *(test)* Run migrations for sqlite
- *(test)* Remove extra newline
- Split ranking regex lines

### Ci

- Add workflow for automatic changelog

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

### Refactor

- Wrap long SQL strings

### Style

- Add custom file input
- Enhance catalog comment card
- Enhance rotate button appearance
- Improve mobile layout for events
- Unify vue quiz buttons
- Unify admin question buttons
- Wrap long SQL strings in TenantService

### Test

- Switch service tests to use sqlite
- Verify tenant creation and removal
- Fix schemas for event-aware tables
- Verify catalog create and delete via db
- Expect marketing pages on main domain

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

