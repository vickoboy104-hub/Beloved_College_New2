# Public Website, CMS and Theme Manager

This release replaces the public placeholder with a complete Laravel-managed school website and adds a permission-controlled content and theme administration system.

## Public website

The root public surface now includes:

- dynamic homepage
- About page
- Admissions information
- Contact page and stored enquiries
- published news and announcements
- individual news pages
- public gallery
- newsletter subscription with recorded consent
- public result checker
- Student, Parent and Staff portal entry points
- school telephone, email, WhatsApp and social information

Default content keeps the public website usable before administrators save the first CMS records. Saved and published CMS content takes precedence over fallback content.

## Homepage

The homepage supports:

- image or video hero rotation
- accessible fallback hero
- automatic or manually configured statistics
- school welcome content
- programme descriptions
- published news
- gallery media
- testimonials
- admissions calls to action

Hero media is ordered by the configured display position. Uploaded media remains on Laravel's local private disk and is delivered only through published model-authorized routes.

## Public CMS

Users with `website.manage_content` can manage:

- homepage, About, Admissions and Contact content
- SEO titles and descriptions
- page draft and publication state
- hero, campus and gallery media
- media titles, captions and alternative text
- news and announcements
- testimonials
- contact-message status
- newsletter consent records
- public school identity and contact details
- social links and homepage behavior

The default permission matrix gives this capability to Admin and Super Admin. Principal, Accountant, Teacher, Parent and Student roles do not receive it automatically.

## Contact enquiries

Public contact messages are stored before any email delivery attempt. The configured school recipient receives a queued notification when mail delivery is configured. Queue or mail-delivery failure cannot remove the saved enquiry or cause the public submission to be lost.

## Newsletter consent

Newsletter subscriptions record:

- normalized email address
- optional name
- explicit consent
- consent-policy version
- consent timestamp
- unsubscribe timestamp
- subscription source

Resubscribing reactivates the same email record instead of creating duplicates.

## Exactly two themes

The platform continues to support exactly:

- Classic
- Dark

Classic defaults use royal blue, yellow and tinted-white surfaces. Dark defaults use midnight navy, layered blue surfaces, electric blue and yellow accents.

## Semantic tokens

Both themes expose semantic tokens for:

- page background
- main surface
- muted surface
- strong surface
- primary colour
- strong primary
- soft primary
- accent
- soft accent
- main text
- muted text
- borders
- success states
- danger states
- public/portal header
- header text

Tokens are six-digit hexadecimal colours. The Theme Service rejects published combinations when main text against the page or header text against the header falls below a 4.5:1 contrast ratio.

## Draft, publish and rollback

Theme administration includes:

- synchronized colour and hexadecimal inputs
- live panel preview
- desktop and mobile preview surface
- draft revisions
- published revisions
- revision notes
- creator and timestamp history
- transactional publication
- non-destructive rollback

Rollback publishes a new revision using the selected historical tokens. It does not delete previous history.

## User theme selection

Admin can:

- force one global default theme
- permit authenticated users to choose Classic or Dark
- disable user choice and return all users to the global default

User preference is stored in the existing `preferred_theme` field. All public pages, portal layouts, result-checker screens and payment callbacks resolve their colours through the published semantic token set.

## Data preservation

This release adds only missing CMS tables:

- `cms_pages`
- `website_media`
- `testimonials`
- `newsletter_subscribers`
- `theme_revisions`

Existing legacy-compatible tables remain in use:

- `announcements`
- `contact_messages`
- `settings`

Rollback does not drop public content, subscriptions, media history or theme revisions.

## Automated verification

The release tests:

- public page rendering
- contact-message storage
- queued on-demand contact notification
- newsletter consent and resubscription
- future and draft news visibility
- CMS role boundaries
- page publication and public visibility
- uploaded media storage and publication access
- public school identity settings
- CMS and theme schema
- Classic theme publication
- revision replacement
- rollback history
- contrast rejection
- controlled user theme selection
- Theme Manager access boundaries
