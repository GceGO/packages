# Architecture

```
Blueprint (registry)
├── Source              gcego/data: model, scopes, policies
└── Infolist            entries, layouts, record actions
        │ SchemaCompiler (pure, cached): entry access paths + relation candidates
        ▼
InfolistManager
├── resolve()        registered blueprint only
├── authorize()      gcego/security requirements of blueprint and infolist
├── record()         SecurityManager::record(source + With, key, 'view'): 404 / 403
├── access()         entry FieldRules for this user and record
└── renderRecord()   frame ──► Runtime ──► InfolistRenderer (article)
                                           ├── LayoutRenderer  Section, Grid, Tabs, Tab, Split
                                           └── EntryRenderer   <div><dt>label</dt><dd>value</dd></div>
```

## Who owns what

| Concern | Owner |
| --- | --- |
| Record lookup, scopes, tenancy | gcego/data and gcego/security (`RecordAuthorizer`) |
| Entry and section authorization | gcego/security (`FieldRule`, requirements) |
| Number, money, date formatting; icons; URL checks; value reading | gcego/tables `Support\Formats`, `Icons`, `SafeUrl`, `ValueResolver` (shared) |
| Record actions | gcego/actions (`ActionHost`, placement `record`) |
| Edit input | gcego/forms (through gcego/actions) |
| Definitions, layouts, presentation, rich-text sanitizing | gcego/infolists |

## Lifetimes

| Lifetime | Services |
| --- | --- |
| Singletons | `InfolistsConfig`, `InfolistManager` (schema and source caches) |
| Scoped | the render context, the inspector |

User-dependent services are resolved per call.
