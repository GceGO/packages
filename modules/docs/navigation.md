# Navigation

`ModuleNavigationContributor` is registered with gcego/navigation automatically. It adds to `gcego-modules.navigation.target` (default `admin`, which must be a registered navigation) one group per module that opts in:

- `Module::Navigation`, or `navigation.auto` for every module;
- group label: `NavigationGroup`, else the title; order: `NavigationOrder`.

Each group holds:

- **resources with a list page:** label `NavigationLabel` or the plural label, order `NavigationOrder` or the declaration order, the resource icon;
- **the module's pages without parameters.**

Items carry their page's requirements, so gcego/navigation shows them exactly to users who may open the page, per tenant. Items are active on the page and on every page of the resource (`gcego.m.r.*`), its relationship pages included. Pages with parameters (record pages, relation pages) are never added. A tenant route parameter becomes `:tenant`.
