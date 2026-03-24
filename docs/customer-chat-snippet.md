# Customer Chat Snippet (CMS Collaboration)

Use this as the standard kickoff message when collaborating with a customer in a shared chat.

## TL;DR kickoff message (copy/paste)

**Context:** We are working with **Customer: <NAME>** on **Namespace: `<namespace>`**.

**Scope:**
- Only CMS changes for namespace `<namespace>` (pages/blocks + optional SEO/menu if explicitly requested)
- No unrelated system/admin actions
- No secrets other than the namespace-scoped CMS API token

**Token handling:**
- Customer provides a **namespace-scoped Bearer token** (minimal scopes)
- Token is treated as **temporary** and will be **rotated/revoked after the task**
- Never post GitHub PATs, SSH keys, or passwords in chat

**How we work:**
- We do changes via **`PUT /api/v1/namespaces/<namespace>/pages/<slug>`**
- API returns **422 with validation details** if blocks violate the contract
- After updates we confirm the result with:
  - Admin editor loads without contract errors
  - Preview link works (draft preview uses `?preview=1`)

**Confirmation:**
- Please confirm: (1) namespace, (2) requested page slug(s), (3) whether draft vs published

---

## Minimal scope recommendations

- Content-only edits: `cms:write`
- SEO edits: `cms:write, seo:write`
- Menu edits: `cms:write, menu:write`

---

## Safety rules

- Never reuse customer tokens across namespaces
- Always log which page slug(s) were changed (for auditability)
- Rotate/revoke tokens after the session
