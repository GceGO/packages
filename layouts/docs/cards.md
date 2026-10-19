# Cards

```php
new Card('Enrolment', new Actions(new Action('export', 'Export')), new LineChart(…), new Footer(…))
new Card(new Property(Card::Variant, 'warning'), new Property(Card::Label, 'Overdue fees'), …)
new Card(new Form(…))   // untitled: a plain bordered container
```

| Property | Values |
| --- | --- |
| Label, Description | shorthand |
| `Icon` | a gcego icon name |
| `Variant` | `default`, `muted`, `outline`, `elevated`, `info`, `success`, `warning`, `danger` |
| `Padding` | `none`, `sm`, `md` (default), `lg` |
| `Empty` | empty text, as for sections |

Behavior:
- **Regions.** Cards take the same regions as sections (`Actions`,
  `Footer`) and hide themselves when nothing in them renders.
- **Markup.** With a title, a card is a labelled `<section>`; without one,
  a `<div>`.
- **Variants.** Tone variants add a coloured start border. Colour is
  never the only signal, so say what the card is about in its title.
- **Theming.** Every variant uses gcego tokens, so dark mode and themes
  apply.

Card types are not business-specific: there are no "stat card" or "invoice
card" classes. Compose them from a card and the components inside it.
