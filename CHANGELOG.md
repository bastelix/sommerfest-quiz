# Changelog

## [unreleased]

### Dokumentation

- Statistik-Tab beschrieben

### Chore

- Cascade delete events
- Pin GitHub Actions to SHA
- Apply unless-stopped restart policy

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
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Add tenant API section
- Update changelog [skip ci]
- Add tenant features section
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Clarify event table columns
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Fix shell commands for alpine
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]
- Update changelog [skip ci]

### Feat

- Skip schema init when db ready
- Support soft hyphen placeholders
- Add dynamic congratulations for team PDFs
- Add event API and schema columns
- *(admin)* Activate event selection
- Confirm event deletion
- Centralize roles and update management
- Seed users for all roles

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

### Refactor

- Wrap long SQL strings

### Style

- Add custom file input
- Enhance catalog comment card
- Enhance rotate button appearance

### Test

- Switch service tests to use sqlite
- Verify tenant creation and removal

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

