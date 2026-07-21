# Interface Principles

## Objective

The new interface must feel like one coherent school operating system rather than a collection of unrelated cards and patched pages.

## 1. Flat hierarchy

Avoid decorative nesting such as:

```text
Card
└── Card
    └── Box
        └── Form box
```

Preferred structure:

```text
Page
├── Heading and actions
├── Optional metrics
├── Filters or tabs
└── Direct content
```

Use borders, spacing, typography and background changes to establish hierarchy before adding another container.

## 2. When a card is justified

A card is appropriate when the content is an independent object, for example:

- Dashboard metric
- Announcement
- Assignment
- Invoice
- Child selector
- Mobile summary item

A card is not appropriate merely to surround a table, then surround every row with another box.

## 3. Desktop workspace

- Collapsible left navigation.
- Compact top bar for search, notifications, theme and account actions.
- Main content uses the available width.
- Complex records open in a drawer or dedicated page.
- Tables use sticky headings and predictable action placement.
- Bulk actions remain visible after selection.
- Page actions remain close to the page title.

## 4. Mobile workspace

- Primary navigation is reachable with one hand.
- Major daily actions may use bottom navigation.
- Forms use a single column unless two short fields clearly fit.
- Tables transform into purposeful record rows; they are not simply squeezed.
- Primary actions may be sticky where this prevents excessive scrolling.
- Drawers and dialogs fit the viewport and preserve keyboard access.
- No workflow depends on hover.

## 5. Responsive behavior

Responsive design is structural, not a CSS afterthought.

Each page is designed for:

1. Phone.
2. Tablet.
3. Laptop.
4. Wide desktop.

The full web portal remains usable on mobile, and the app portal remains usable on tablets. Automatic device routing never removes the user's ability to switch portal surface.

## 6. Themes

Exactly two themes exist.

### Classic

- Royal blue primary identity.
- Yellow accent.
- Tinted-white page background.
- White or softly tinted surfaces.
- Dark navy text.
- Strong but controlled status colors.

### Dark

- Midnight navy page background.
- Blue-black surfaces.
- White primary text.
- Muted blue-gray secondary text.
- Royal or electric blue primary actions.
- Yellow accent retained for identity and important emphasis.

Themes use semantic variables. Components do not define arbitrary theme-specific hex values unless approved as part of the design system.

## 7. Admin theme control

Admin may edit approved tokens for Classic and Dark.

Required controls:

- Live preview.
- Mobile and desktop preview.
- Draft and publish states.
- Reset token.
- Reset theme.
- Version history.
- Contrast warning.
- Safe fallback values.

Admin may force one theme or allow users to select between Classic and Dark.

## 8. Typography

- One display family at most.
- One highly readable interface family.
- Clear page, section and field-label scale.
- Avoid oversized headings that reduce working space.
- Numerical and financial data use tabular numerals where possible.

## 9. Forms

- Labels remain visible; placeholders do not replace labels.
- Related fields are grouped with headings and spacing, not nested boxes.
- Validation appears beside the field and in a concise page summary when needed.
- Destructive actions are visually distinct and require confirmation.
- Long forms support saved progress or staged sections where appropriate.
- Mobile keyboards and input modes match the expected data.

## 10. Tables and records

- Search and filters sit directly above the data.
- Essential columns remain visible.
- Secondary columns may be toggled or moved to a details panel.
- Row actions use a consistent final column on desktop.
- Mobile records prioritize identity, status and the most important action.
- Empty states explain the next valid action.

## 11. Feedback and behavior

Every action must communicate one of:

- In progress.
- Successful.
- Failed with a clear reason.
- Awaiting approval.
- No change required.

Prevent duplicate submission and preserve user input after validation errors.

## 12. Accessibility

- Keyboard navigation for all controls.
- Visible focus states.
- Semantic headings and landmarks.
- Proper labels and error associations.
- Sufficient color contrast.
- Status is not communicated by color alone.
- Reduced-motion preference is respected.
- Touch targets meet practical mobile sizing.

## 13. Performance

- Paginate large data sets.
- Lazy-load secondary panels.
- Queue slow operations.
- Optimize uploaded images.
- Avoid loading complete school directories into mobile pages.
- Use Livewire updates only for the smallest necessary component region.

## 14. Definition of interface completion

A page is not complete until:

- Its permissions are enforced server-side.
- Loading, empty, success and error states exist.
- Phone, tablet and desktop behavior is verified.
- Classic and Dark themes are verified.
- Keyboard navigation is tested.
- The page has no accidental horizontal overflow.
- The primary task can be completed without navigating through decorative containers.
