<?php

return [
    'meta_title' => 'What\'s new — idexal Os',
    'meta_description' => 'Every published release of idexal Os, newest first: what changed, why it matters, and which build is current.',
    'eyebrow' => 'Release notes',
    'title' => 'What\'s new',
    'heading' => 'Every release, in plain language.',
    'intro' => 'What changed, why it matters, and which build is current. This page is the public record of the product, written for the people who use it.',
    'current' => 'Current release',
    'released' => 'Released :date',
    'permalink' => 'Link to release :version',
    'cta_title' => 'Download the current build',
    'cta_body' => 'Installers for Windows, macOS and Linux, with a checksum you can verify before installing.',
    'cta' => 'Go to downloads',
    'back_home' => 'Back to home',

    'releases' => [
        '3_14_1' => [
            'title' => 'Linux builds are published: AppImage, deb and a portable archive',
            'summary' => 'The download page offered Windows only. The desktop app is now built for Linux in the same release pipeline and published with the same checks: a portable AppImage, a Debian package and a tar.gz archive, each with its own sha512 in the manifest. The release pipeline itself gained the two pieces it was missing — the packaging scripts that .gitignore excluded from the source tree, and the project homepage deb/rpm metadata needs.',
            'changes' => [
                'Linux x64: idexal-OS-3.13.0.AppImage, idexal-OS-3.13.0.deb and idexal-OS-3.13.0-linux.tar.gz, served from /download with checksums.',
                'The release snapshot now includes apps/desktop/build/**, so deb and dmg packaging have their after-install scripts and icons.',
                'apps/desktop/package.json declares its homepage, which deb and rpm metadata require; the rpm target stays off until the package name is valid.',
            ],
        ],
        '3_14_0' => [
            'title' => 'The database is backed up, and Windows arm64 ships',
            'summary' => 'The backups taken at every deploy copy the application files, not the rows, so a restore would have rebuilt an empty platform. A nightly dump now writes a compressed copy of the database, keeps the last fourteen, and the readiness page fails when the newest dump is missing or older than a day. The Windows arm64 installer is built from the same code as x64 and published with its checksum.',
            'changes' => [
                'A backup:database command writes a gzipped MySQL dump to storage/app/backups and prunes beyond the retention window; it runs nightly at 02:30.',
                'Readiness gains a database check: blocked when no dump exists, attention when the newest is older than 26 hours.',
                'Windows arm64 installer 3.13.0 built and published beside x64, with its own sha512 in the download manifest.',
                'The dump password travels in the process environment, never in the command line or the process list.',
            ],
        ],
        '3_13_2' => [
            'title' => 'A new workspace can choose the free plan, and the desktop opens',
            'summary' => 'The plan chooser marked the free plan as “current” because it was the display fallback for a workspace with no subscription, and a current plan has no action. The result was a dead end: a new account could not choose free, paid plans without a gateway only created invoices, and the desktop stayed closed. The free plan is now a real choice whenever there is no subscription, and the header says “No plan” until one is chosen. The Windows installer is rebuilt from this code line, the first new build since 3.10.2.',
            'changes' => [
                'Billing: a workspace with no subscription sees “No plan”, a prompt, and a Choose plan action on every public plan, free included.',
                'Choosing free starts the subscription immediately and flips the desktop gate from closed to open; a regression test covers the whole chain.',
                'Registration copy no longer shows raw :terms and :privacy placeholders; the links follow a plain sentence in both languages.',
                'Windows x64 installer rebuilt at 3.13.0 and published on the download page with its checksum.',
            ],
        ],
        '3_13_1' => [
            'title' => 'The console now says what is still missing before customers find it',
            'summary' => 'A new Readiness page checks the states that break a launch silently, against the live deployment: mail transport, payment gateway, scheduler heartbeat, queue failures, storage link, catalogue size and the desktop gate. Each row says what is wrong and what to do. The checks come from failures this deployment actually had: mail was left on the log transport, so password resets and invoices were never delivered, and no payment gateway was enabled.',
            'changes' => [
                'Admin → Readiness lists nine checks with Ready / Attention / Blocked and a concrete fix for each.',
                'The scheduler now writes a heartbeat every minute, so the page can tell whether cron is running rather than whether it is configured.',
                'Paid plans with no gateway are shown as Attention with the manual-invoice fallback explained, not as a silent gap.',
                'Operations cleanup on this deployment: a hardcoded GitHub token and a daily deploy from a repository that does not exist were removed from cron, and a public debug file that exposed log lines was deleted.',
            ],
        ],
        '3_13_0' => [
            'title' => 'You can read a skill before you install it',
            'summary' => 'A store that asks for trust should show what it is selling. Every skill page now prints the opening of the skill’s own instruction file — the text the agent actually reads — instead of repeating a one-line summary. Related entries are ranked by what they have in common rather than by an empty tag, and a curation marker is no longer shown to visitors as if it were a category. In the desktop app, a plan with no providers assigned now says so instead of showing nothing at all. This release also adds DESIGN.md, the design rules the product had been shipping without.',
            'changes' => [
                'Skill pages show the first 1,400 characters of the real SKILL.md, with the source link to the full file.',
                'Related entries are matched on shared words in the name and description, so “accessibility” leads to other audit and testing skills.',
                'Category labels are cleaned: a top-level skill no longer repeats its own name, and the internal .curated marker never reaches the page.',
                'Desktop Settings → AI states “No providers are assigned to this plan yet” when an operator has turned on explicit assignment but assigned none.',
                'DESIGN.md documents the colour, type, layout, navigation, motion, RTL and accessibility rules the site and the app follow.',
            ],
        ],
        '3_12_0' => [
            'title' => 'Every skill has a page, and the desktop shows what your plan includes',
            'summary' => 'The catalogue listed 288 entries and then handed every one of them to GitHub. Each entry now has its own page — what it does, where it comes from, how it reaches a workspace, and the entries closest to it — and every one of those pages is in the sitemap. In the desktop app, Settings → AI now states which providers the active plan reaches through the gateway, and says so plainly when own keys are switched off.',
            'changes' => [
                'A page per catalogue entry at /integrations/{type}/{slug}: description, type, category, identifier, source, three-step install path and four related entries.',
                'The listing and the featured shelf link to those pages instead of jumping straight to GitHub; the source link is still there for readers who want it.',
                'All 288 entry pages are published in sitemap.xml, with a language alternate each, so a search for a specific skill can land on it.',
                'The desktop AI settings panel lists the providers the plan covers and explains that own provider keys are disabled when the operator turned them off.',
                'Catalogue pages are translated, right-to-left included.',
            ],
        ],
        '3_11_2' => [
            'title' => 'A header menu that behaves like a product, not a dropdown',
            'summary' => 'The navigation panels were built for a list of links, not for the eight products and ten use cases the platform actually has: one narrow column, no icons, no descriptions, an instant open on hover, and an Escape key that reopened the panel because returning focus counted as opening it. The menu is now a real mega menu, in both directions.',
            'changes' => [
                'Products, use cases and resources each open a structured panel: two columns of labelled entries with icons and one-line descriptions, plus a highlighted start-free card on the products panel and a footer row with the section link and its neighbour.',
                'Opening waits 90ms and closing waits 180ms, so brushing past a label does not flash a panel and moving diagonally into it does not close it.',
                'Keyboard support is real: focus opens the panel, Down moves into the first entry, Escape closes it and returns focus to the label without reopening, and tabbing out closes it.',
                'A click anywhere outside closes the panel, and every panel is capped to the viewport width so it cannot overflow on a narrow window.',
                'Right-to-left is handled with logical start/end alignment rather than a mirrored copy, and the arrow icons flip with the text direction.',
            ],
        ],
        '3_11_1' => [
            'title' => 'The skills catalogue is never empty again, and a new account must choose a plan',
            'summary' => 'Three promises the product made but did not keep, fixed at the source. The public catalogue rendered "0 items" whenever GitHub was slow or rate-limited, because the page had no bundled fallback; it now ships the full skills library with the application. A new desktop account could open the app on an implicit free tier; choosing a plan is now the default and the app stays closed until one is chosen. And the header wrapped into two rows between 1024px and 1350px, detaching dropdown arrows from their labels in both English and Arabic.',
            'changes' => [
                'The catalogue now ships with the application: 285 skills, connectors and plugins are bundled, and GitHub is an optional enrichment rather than the source of truth. The store is paginated, so every entry is reachable.',
                'Choosing a plan is required by default: a new account is created without a subscription and the desktop holds at the plan chooser until a plan — free or paid — is selected. Operators can still relax this in the admin console.',
                'The site navigation stays on one row at every width and in both directions; below 1280px it collapses into the drawer, and the sign-up action remains visible on tablets.',
                'A consistency test now pins the version the API reports to the newest entry on the release page, so the two can no longer disagree.',
            ],
        ],
        '3_11_0' => [
            'title' => 'The home page now says what the platform is: one gated account across desktop, web, and gateway',
            'summary' => 'The landing page led with the developer API angle and left out the one fact that defines the product: it is a single signed-in account - a plan or a free tier - that runs the desktop, the web console, and the gateway, with the model catalog set by the operator. The home page now states that up front, so a visitor understands before they download that the app opens only after they sign in.',
            'changes' => [
                'A new “one account, the whole platform” section sits at the top of the page: sign-in-or-plan is required first, the operator controls which models, providers and features each plan reaches, and one login drives desktop, web, and gateway.',
                'The section is fully translated, right-to-left included, and uses the same design system and icon set as the rest of the page rather than a new visual style.',
            ],
        ],
        '3_10_2' => [
            'title' => 'The chat-copy window stops coming back every time you open the app',
            'summary' => 'A chat copy runs on your computer, so closing the app never stopped it — but the choice to continue in the background lived only in the window, so every launch put the same still-running copy back on screen as a blocking dialog. The app now remembers that choice, and a copy already sent to the background is shown as a quiet banner instead.',
            'changes' => [
                'Choosing “Continue using idexal Os” is remembered: opening the app again no longer throws the same migration window over your work.',
                'A copy still running in the background appears as a small banner, not a dialog you have to dismiss on every launch.',
                'When the copy finishes the memory clears, so a genuinely new migration still asks once, as it should.',
            ],
        ],
        '3_10_1' => [
            'title' => 'Providers belong to your plan, and the app says so',
            'summary' => 'With members’ own keys turned off — the default — the app no longer offers a place to add provider keys. The providers screen explains that your organization manages the connections, and your agents use the models your plan includes.',
            'changes' => [
                'The provider screen no longer shows an add-your-own-key surface while own-keys are disabled for the deployment.',
                'It says plainly that provider connections are managed by your organization, and that the models from your plan are included automatically through the gateway.',
            ],
        ],
        '3_10_0' => [
            'title' => 'The installer looks like the app again, and tells the truth about first launch',
            'summary' => 'The setup wizard had been left behind by the rebrand: it still opened with the previous logo, the retired palette and a hardcoded “Version 1.0.0”, and its welcome page still promised the app “can run without an account” — which stopped being true when the first-launch gate arrived. The wizard is rebuilt from the current mark, the stale version is gone, and the welcome page now says what actually happens. Windows and Linux builds are prepared; the macOS build follows.',
            'changes' => [
                'The setup wizard now shows the current logo and palette, on the sidebar and the header, instead of the previous brand.',
                'The licence page no longer pins “Version 1.0.0” — the version is shown by the wizard itself and in the app’s About screen, so it can never go stale again.',
                'The welcome page now says the app opens with an account: sign in, or start a subscription and choose a plan, and the workspace opens once either is done.',
                'The bundled documentation no longer describes signing in as optional, and no longer says the desktop app needs no sign-in.',
                'The providers screen can now require an assignment: by default a model or provider you never assigned to a plan still serves every plan, and one switch changes that so only what you assign reaches a plan — the desktop and the gateway read the same switch, so they can never disagree.',
                'Windows installers and Linux packages (AppImage, deb, rpm and a portable archive) are built from the same release — Linux desktop packages are produced by the release pipeline for the first time, together with a Windows arm64 installer.',
            ],
        ],
        '3_9_0' => [
            'title' => 'The first screen is now part of the app: sign in or pick a plan without leaving it',
            'summary' => 'The mandatory gate told people to finish signing in “in their browser”, and a brand-new account that signed up instead of signing in was dropped on the dashboard with no way back to the app. The gate now opens the platform inside a window of the app itself and returns to the app the moment the account is connected, and signing up carries straight through to connecting the desktop.',
            'changes' => [
                'Signing in from the first screen now opens inside the app — the same sign-in and plan pages, in a window of the app — so nothing sends you off to a browser tab.',
                'Creating a new account from that screen finishes by connecting the app: the platform returns the connection automatically instead of leaving you on a dashboard inside the sign-in window.',
                'When a plan is required, a new account is taken to the plans first; as soon as a plan is active the app is connected, whether the plan was chosen directly or completed at a hosted checkout.',
                'If the in-app window cannot open for any reason, the browser is used instead, so the first screen can always be passed.',
                'The download page now says what happens on first launch, so an account is expected before the installer is run rather than discovered after.',
            ],
        ],
        '3_8_0' => [
            'title' => 'One screen to control every provider, and to cancel members’ own keys everywhere',
            'summary' => 'The providers screen listed each provider but never said which plans it belongs to, and there was no way to switch one off without opening its form — while the proxy manager next door did both. It now matches: every provider shows the plans it serves, an enable/disable action sits on the row, and one switch cancels members’ own API keys for the whole platform.',
            'changes' => [
                'Each provider now shows the plans it belongs to as badges, or “all plans” when it is assigned to none — so it is clear at a glance who gets it.',
                'A provider can be turned on or off straight from the list. Switching one off takes every one of its models out of every desktop immediately.',
                'One platform-wide switch cancels members’ own API keys for everyone: no plan and no workspace setting can bring them back while it is off, so every member uses the plans you publish.',
                'A plan that explicitly grants own-keys still works as an exception when the platform switch is on — this switch is the one that outranks it.',
            ],
        ],
        '3_7_0' => [
            'title' => 'You decide who inside a workspace may use each plugin',
            'summary' => 'The Library’s access screen asked a server that never answered, so it was dead. Access is now real: a plugin is available to the whole workspace by default, an operator can name the members who may use it, and those names decide what the app lists. The one thing this server cannot do — teams — is refused with a plain reason rather than stored as something else.',
            'changes' => [
                'An operator can grant a plugin to the whole workspace, or only to named members, from the Library’s access screen.',
                'The grant decides what the app shows: a member who is not entitled to a plugin does not see it at all.',
                'A plugin nobody has been granted stays available to everyone, so installing one keeps working exactly as before.',
                'Revoking a grant leaves the plugin installed and simply stops naming the people it named.',
                'Teams are refused with an explanation, because this server has no teams to grant to.',
            ],
        ],
        '3_6_0' => [
            'title' => 'A plan can carry a monthly spend allowance, and a member can ask for more',
            'summary' => 'Until now a member who used up their plan had nowhere to ask. The plan can now carry a monthly spend allowance, the member sees it against this month’s real spend, and when it is gone they ask an operator from the app. The operator approves an extension or declines, the member is told either way, and the allowance they see grows by exactly what was approved.',
            'changes' => [
                'A plan can set a monthly spend allowance, and the desktop shows it against the month’s actual cost as a budget it can watch — not as a hard block, because the hard stop stays the plan’s request and token limits.',
                'When the allowance is used up, the member asks for more from the app and the ask lands in the operator’s queue on the gateway screen, with the numbers in front of them.',
                'Approving adds an extension to that member’s workspace for the period they asked about; declining closes it and tells them, and either way they can ask again next month.',
                'An ask that is already waiting is not asked again: the app says it is waiting instead of offering the button twice.',
            ],
        ],
        '3_5_0' => [
            'title' => 'Every plugin row says what the plugin is made of',
            'summary' => 'A plugin in the Library is not one thing: it is the skills, commands and connections inside it. The platform knew what those were and never said, so each row read as empty of components. The row now reports them, grouped by kind and counted, which is what the desktop already asks for.',
            'changes' => [
                'Each plugin row in the Library reports the kinds of components inside it and how many — for example “3 skills and 1 connection” instead of nothing.',
                'It is derived from the plugin’s own catalogue definition, so it can never list a component the plugin does not contain.',
                'A plugin that declares no members counts itself, so a row still says something true rather than nothing.',
            ],
        ],
        '3_4_0' => [
            'title' => 'The Library loads in one request, and the last of the integration is accounted for',
            'summary' => 'Two more pieces of the desktop’s API are now answered by the platform, and the rest of the surface was audited one call at a time so that nothing is silently missing. What the desktop asks for beyond this is either behind a feature the platform reports as off, or something the platform genuinely does not run — and each of those is named rather than left to guess.',
            'changes' => [
                'The Library resolves a whole page of plugins in one request instead of one request per plugin, and one plugin missing from the catalogue no longer fails the batch — it is reported on its own.',
                'The single-plugin and batched resolvers share one implementation, so they can never disagree about what a plugin contains.',
                'Every endpoint the desktop calls was called against the platform with a real member key. The ones that still answer “not found” are listed with the reason they cannot be served here, so the integration is accounted for rather than assumed.',
            ],
        ],
        '3_3_0' => [
            'title' => 'The Library and the desktop agree on what your server offers',
            'summary' => 'The desktop asks a platform for a lot of small things. On a server that does not run all of them, some asked for a page that was never there and showed an error instead. The desktop now knows the difference between "this server does not offer that" and "something went wrong", and says so plainly — and the Library’s list of what your workspace can use is served by the platform at last.',
            'changes' => [
                'The plugin list and each plugin’s access list are now answered by the platform, so the Library loads instead of failing. Access is reported as it really is here: the whole workspace, not a per-team list.',
                'When a server does not implement an optional part of the desktop’s API — MCP discovery, for instance — the desktop now shows it as unavailable on this server rather than as an error.',
                'Nothing changes on a server that does implement them: the same calls still succeed and return what they always did.',
            ],
        ],
        '3_2_0' => [
            'title' => 'Your Claude models reach the desktop, not just the API',
            'summary' => 'The desktop speaks one API shape. Any provider that speaks a different one — Anthropic, for instance — was therefore invisible in the app even when the plan granted it: the operator enabled the models, the plan covered them, and the desktop still showed nothing. The gateway can now translate in both directions, so those models appear and work like any other, and an operator chooses when to turn that on.',
            'changes' => [
                'A switch lets the gateway serve Anthropic (Claude) models to the desktop: the request is rewritten into the shape Anthropic expects, and the answer — including streamed replies and tool calls — is rewritten back into the shape the app expects.',
                'It is off by default, so nothing changes until an operator turns it on, and turning it back off hides those models again exactly as before.',
                'The gateway keeps serving the same models to API clients through both the OpenAI and the Anthropic endpoints, whichever they prefer.',
            ],
        ],
        '3_1_0' => [
            'title' => 'You can see why a model reaches the desktop — and require a plan first',
            'summary' => 'A member who signed in and still saw no models had no explanation, and neither did the operator. The gateway screen now shows what a desktop actually receives, and names the gate that dropped every model that is missing — a provider with no key, a provider assigned to other plans only, an inactive provider. An operator can also require every new account to choose a plan before the desktop opens.',
            'changes' => [
                'The gateway screen gained a “Desktop delivery” panel: pick a workspace and see its plan, how many models reach the desktop, and every missing model with the reason and the fix.',
                'The panel warns loudly when there is no “free” plan (so a brand-new account has no plan at all) and when every active provider is missing an API key — the two ways a whole platform ends up serving no models.',
                'A new switch makes a new account choose a plan: no free plan is granted automatically, and the desktop holds at the plan chooser until one is picked.',
            ],
        ],
        '3_0_0' => [
            'title' => 'The operator gets full control over every account',
            'summary' => 'The Users screen in the admin panel was a list with an edit form. It is now a complete console: every account has a page of its own with its workspaces, roles, API keys, desktop sessions and history; an operator can create accounts, suspend and restore them, verify an email, send a password reset, revoke every key or sign a person out everywhere, act on many accounts at once, and export the list. The guards that keep a platform from locking itself out are built in.',
            'changes' => [
                'Every account now has its own page: profile, the workspaces and roles it belongs to, its API keys with abilities and expiry, its desktop sessions with device and address, and its recent history.',
                'An operator can create an account directly, with or without a temporary password, and can optionally place it in a workspace with a role in the same step.',
                'Suspend and restore are one click, and suspending revokes the account\'s keys and signs out its desktops at the same moment. Verification can be granted by hand, and a password reset link can be sent without waiting for the person to ask.',
                'Keys and sessions can be revoked on their own, without suspending the account, and the list can be filtered by role, status, verification, workspace and activity, or exported as a spreadsheet.',
                'Several accounts can be changed at once, and the platform always keeps one admin: an operator cannot suspend, demote or delete their own account, nor the last remaining admin, nor an account that still owns a workspace.',
            ],
        ],
        '2_9_0' => [
            'title' => 'You control every provider, and every key, from one screen',
            'summary' => 'The providers your members can use are no longer whatever the platform happens to have. An operator decides, from the admin panel, which providers are on, which plan each one belongs to, and whether members may add their own API keys at all — and the desktop shows exactly that decision, the same way it already does for network proxies.',
            'changes' => [
                'Every provider can be switched on or off platform-wide, and each one can be assigned to specific plans; a provider assigned to no plan is offered to every plan, so nothing changes until you choose to restrict it.',
                'A single control on a workspace forces members to use the plan: their own API keys stop being offered on every connected desktop, and every provider key they had added is simply not delivered any more.',
                'A plan can still opt back in, so a special plan can keep the “add your own key” option while everyone else is on plans only.',
                'The desktop is told the result — which providers the plan covers and whether own keys are allowed — and shows it, so a member never sees a provider they cannot use.',
            ],
        ],
        '2_8_0' => [
            'title' => 'The desktop app requires a plan, and you pick it before the app opens',
            'summary' => 'Installing the desktop app no longer lets you start using it anonymously. The first thing you see is a sign-in screen: an existing account, or a new one with a plan. Once you are in, your organization\'s plan decides whether the app opens at all — and the operator can lock every provider on and off from one screen, including whether anyone may bring their own keys.',
            'changes' => [
                'Installing the desktop app now opens straight into a sign-in screen. There is no "use without an account" path on the public installer; you sign in to an existing account or create a new one with a plan, and only then does the app open.',
                'If your account\'s plan does not include the desktop app, the app shows a "pick a plan" screen instead of your workspaces, and opens the moment your plan grants access — no relaunch needed.',
                'An operator can switch on the plan requirement for the whole deployment in one click; while it is off, every plan grants access and nothing changes.',
                'The same operator screen decides which providers are visible at all, and whether members may add their own API keys — a global kill switch that still lets a plan opt back in.',
            ],
        ],
        '2_7_0' => [
            'title' => 'Your language follows every link, and the desktop app speaks Arabic',
            'summary' => 'The public site now carries the language you chose through every navigation link, the footer says what it means, and a new What\'s new page records each release. The desktop app adds Arabic and a right-to-left layout, starting with the workspace you use every day.',
            'changes' => [
                'Every internal link keeps the language you chose, so the page you copy from the address bar is the page the next person reads.',
                'A new What\'s new page lists each release in English and Arabic, with structured data and a place in the sitemap.',
                'The footer no longer reads "All Terms of Service"; it says "All rights reserved." in both languages.',
                'The desktop app offers العربية in Settings › Appearance and flips the whole interface to right-to-left immediately, covering the everyday workspace with English as the fallback for anything not translated yet.',
                'The home page plays the product in motion as a 288 KB video instead of a 1.5 MB GIF, only while it is on screen, and stays on the poster frame when the visitor prefers reduced motion.',
            ],
        ],
        '2_6_0' => [
            'title' => 'The public site is built to be found and shared by anyone, anywhere',
            'summary' => 'Every public page advertises one canonical URL, English and Arabic alternates, a 1200×630 share card and structured data. A sitemap and a robots policy invite search engines in, and the copy speaks to a global workspace rather than one region.',
            'changes' => [
                'Canonical URLs, language alternates and matching social metadata on every public page.',
                'Per-language share cards and structured data for the organization, the site, the app and the FAQ.',
                'A sitemap lists every public page with language alternates, and robots.txt points to it.',
                'English is the anonymous default; Arabic is a first-class language at the same address.',
            ],
        ],
        '2_5_0' => [
            'title' => 'A member can ask an admin for connector access',
            'summary' => 'Approving a connector used to be an operator-only action, so a member waited or asked outside the product. Now the ask is recorded, reaches the organization\'s managers, and is answered once for everyone who asked.',
            'changes' => [
                'A member asks from the Library, and the ask lands in the admin connector queue.',
                'Approving the connector answers every open ask at once, and the member who asked is notified.',
                'A request grants nothing on its own, and the whole surface stays inert while the feature is off.',
            ],
        ],
        '2_4_0' => [
            'title' => 'Connector permissions with an audit trail',
            'summary' => 'Each connector declares the scopes it needs. An operator approves a revision, can revoke it, and every change is written to the audit log and enforced on the desktop.',
            'changes' => [
                'Scoped grants, revisions and revocation for connectors.',
                'The desktop refuses a connector whose grant was revoked.',
                'Every approval and revocation is recorded in the audit log.',
            ],
        ],
    ],
];
