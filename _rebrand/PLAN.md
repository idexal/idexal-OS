# خطة التحول إلى idexal Os (Rebrand Plan)

Repository root: `C:\Users\idexal\Desktop\idexal DeskAssist`
Baseline git commit: `829ef5e` (snapshot before rebrand — يمكن الرجوع إليه في أي وقت)

## الهوية الجديدة / New identity

| نوع | قيمة |
|---|---|
| الاسم الرسمي الكامل | idexal Os Desktop Assistant |
| الاسم المختصر الرسمي | idexal Os |
| camelCase | idexalOs |
| Snake_case | idexal_os |
| kebab-case | idexal-os |
| Env prefix | IDEXAL_ |
| npm scope | @idexal/ و @idexal-ee/ |
| الموقع الرسمي | https://os.idexal.com |
| GitHub | https://github.com/idexal/idexalOs |
| البريد | os@idexal.com / info@idexal.com |
| رابط المشروع (صفحات التواصل) | https://os.idexal.com/idexal-project-os |

## قواعد الاستبدال (بالترتيب — Order matters)

1. **Emails**: support@openworklabs.com → os@idexal.com ; team@/sales@/founders@/ben@/team+security@ → info@idexal.com ; @openworklabs.com → @idexal.com
2. **Domains**:
   - api.app.openworklabs.com → api.os.idexal.com
   - app./www./web.openworklabs.com → os.idexal.com
   - api./diagnostic./models./inference./chat./gateway./schemas./staging-api.openworklabs.com → <same>.idexal.com
   - openworklabs.com (bare) → os.idexal.com
   - openwork.dev → os.idexal.com
   - Aliases: preview.openwork.software → preview.idexal.com ; openwork.software/openworkapp.com/openwork.studio/openworkco.com → idexal.com
3. **GitHub/Docker**: github.com/different-ai/openwork → github.com/idexal/idexalOs ; raw.githubusercontent.com/... → same ; ghcr.io/different-ai/openwork → ghcr.io/idexal/idexal-os ; different-ai → idexal ; Different AI → Idexal ; differentai → idexal
4. **Bundle IDs**: com.differentai.openwork → com.idexal.os
5. **Protocol**: openwork-dev:// → idexalos-dev:// ; openwork:// → idexalos://
6. **Scopes**: @openwork-ee/ → @idexal-ee/ ; @openwork/ → @idexal/ ; @different-ai/openwork-workspace → @idexal/idexal-os-workspace
7. **Special compounds**: opencode-openwork → opencode-idexal-os ; npx openwork → npx idexal-os ; LICENSES refs
8. **Env vars**: OPENWORK_ → IDEXAL_ ; standalone OPENWORK → IDEXAL_OS
9. **HTTP headers**: X-OpenWork-* → X-Idexal-Os-* ; x-openwork-* → x-idexal-os-*
10. **أسماء الكود** (case passes):
    - openWork → idexalOs ; Openwork → IdexalOs ; OpenWork → IdexalOs
    - openwork[A-Z] → idexalOs ; openwork_ → idexal_os ; openwork- → idexal-os ; openwork. → idexal-os ; openwork → idexalos
11. **أسماء الملفات والمجلدات**: نفس القواعد على اسم الملف
12. **نصوص العرض** (pass ثانٍ على الملفات النصية): IdexalOs (كلمة مستقلة) → "idexal Os" في md/mdx/txt/html/locales/email
13. **تعديلات يدوية موجهة**: نصوص الواجهة عالية الظهور (main.mjs dialogs, menu, error screens, session surface, settings copy...)

## ملفات الأصول البصرية (الشعار والأيقونة)

المصدر: `logo_idexal/{dark,light}_{icon,logo}_idexal.png` (RGBA شفاف 377×380 و 1136×380)
الهدف:
- `apps/desktop/resources/icons/` : icon.png (512), icon.ico (16–256), icon.icns (16–1024), icons/linux/16..512, icons/dev/*
- `apps/app/public/` : idexal-os-mark.svg (متجه مرسوم بدقة من PNG), idexal-os-sidebar-mark.svg, idexal-os-logo-square.svg, favicon-16/32, apple-touch-icon 180
- `ee/apps/landing/public/`, `ee/apps/den-web/public/`, `packages/docs/logo/`, `ee/apps/workbot/src/client/public/`
- مكوّنات SVG المضمّنة: packages/ui/src/react/idexal-os-mark.tsx وغيرها من النسخ المضمّنة
- العلامة الكاملة (5 أشكال، تدرج لوني per-shape، userSpaceOnUse) تُرسم في 6 مستهلكين مضمّنين + 5 أصول SVG مستقلة + بلاطة AUR
- النسخة أحادية اللون (all-`#0B0B0B`) للشريط الجانبي فقط
- القفل (lockup): العلامة الكاملة + كلمة dark-ink بصيغة Data URI في x=382 y=73، viewBox `0 0 1136 380`

## إعادة تطبيق العلامة (apply-svgs.py)

تم تشغيل `_rebrand/apply-svgs.py` بنجاح مرتين (مستقر تمامًا):
- كتب 8 أصول SVG + قفلان + بلاطة AUR 1024×1024
- تكرار 6 مستهلكين مضمّن (UI mark، workbot mark، browser-frame، oauth-callback، org-dashboard-shell، og-image)
- تصفية الألوان الداكنة للعلامة الكاملة (8 مستهلكين: `dark:brightness-0 dark:invert`)
- أبعاد مربعة للعلامة في workbot + landing next/image + PKGBUILD Icon=
- صورة البريد الإلكتروني (128×128 من render.png)
- التحقق: كل مسار d مطابق بايتاً لـ mark.json، جميع ملفات SVG XML صالحة، لا بقايا هندسة قديمة خارج `_rebrand/`
- الاختبار `apps/app/tests/provider-icon.test.ts`: أعيد كتابته (viewBox 377×380، 5 مسارات، fillRule undefined، كل مسار M واحد ينتهي بـ Z)

## إزالة تسجيل الدخول والقيود (Desktop: no sign-in, no subscriptions, no limits)

1. `apps/desktop/electron/desktop-distribution.mjs`: التوزيع العام `requireSignin=false` / `requireActivation=false` (مفعل بالفعل)
2. `apps/app/src/react-app/shell/den-signin-routing.ts` + `app-root.tsx`: تعطيل التوجيه إلى /signin و /onboarding الإجباري
3. Gates: DenSigninGate, EnterpriseActivationGate, OpenWorkWebAccessGate → تمرير مباشر (pass-through)
4. حذف/إخفاء مداخل تسجيل الدخول: account-status-menu، session-page empty state، dashboard/automations/calendar/library banners، cloud-providers-view، welcome-page sign-in، den-signin-surface CTA
5. `apps/app/package.json`: `build:web` يحتفظ بـ `VITE_DEN_REQUIRE_SIGNIN=1` (بناء الويب لا يزال مؤمن؛ حزمة سطح المكتب بدون متغير البيئة)
6. إبقاء كود Den الداخلي كما هو حيث الحذف الكامل يكسر البناء، لكن كل المسارات غير قابلة للوصول من الواجهة
7. `apps/desktop/electron/desktop-free-release.mjs`: `IDEXAL_DISABLE_FREE_INFERENCE=1` عند المطابقة بالإصدار (مفعل بالفعل)

## إصلاح أضرار الاستبدال الميكانيكي (rename fallout)

الاستبدال الميكانيكي حوّل بعض الوصول إلى الخصائص `idexalos.x` إلى `idexal-os.x` (تُقرأ في JS كطرح). أُصلحت كل المواضع:

- `components/chat/mcp-app-frame.tsx` (mcpResult/mcpApp)، `components/chat/app-builder-step.tsx` (toolStartedAt/toolCompletedAt)، `components/chat/credential-prompt.ts` (mcpResult)، `domains/connections/cloud-mcp-diagnostics.ts` (compatibility.idexalos)، `domains/settings/pages/advanced-view-sections.tsx` (compatibility.idexalos)
- `apps/server/src/cloud-provider-sync.ts` و `apps/server/src/desktop-cloud-sync.ts`
- وحدة مفقودة: الاستبدال أنشأ `shell/desktop-local-idexal-os.ts` بينما كان الاستيراد `./desktop-local-idexalos` → نُقل التنفيذ الحقيقي إلى `shell/desktop-local-idexalos.ts` (بمُعرّفات نظيفة: `LOCAL_IDEXALOS_READINESS_*`، أحداث `route.local_idexalos.*`) وحُذف الملف المطّلع
- تعليقات `window.__idexal-os` (الاسم الحقيقي `window.__idexalos`) في use-workspace-route-state/dev-profiler/debug-logger/watchdog-overlay/analytics

## بوابة عدم وجود Cloud على سطح المكتب (no-cloud gating)

- `shell/shell-config.tsx`: `cloudSignin: !isDesktopRuntime()` افتراضيًا → كل بوابات الواجهة تعتمد عليه
- `shell/session-route.tsx`: `signedOutDesktopSurfaces` لا تُعرض Dashboard/Automations بدون cloudSignin، وCommandPalette لا يعرض مداخل Automations/Dashboard/Calendar
- `shell/settings-route.tsx`: حجب أوامر Automations في لوحة الأوامر + تحويم روابط `cloud-account/usage/cloud-providers` المباشرة إلى General
- `shell/command-palette.tsx`: أمر "Sign in to idexal Os Cloud" لا يظهر إلا عند تفعيل cloudSignin
- `domains/settings/pages/mcp-view.tsx`: بطاقة تسجيل الدخول/المقفلات في Library تُخفى بدون cloudSignin (الصفوف المحلية "On this computer" تبقى)
- `domains/settings/pages/ai-view.tsx`: نص "Sign in to idexal Os to also get the provider…" يُخفى بدون cloudSignin

## التحقق (Phase 6)

- `pnpm --filter @idexal/app typecheck` و `pnpm --filter idexal-os-server typecheck`: نظيفان
- `apps/app/tests/provider-icon.test.ts`: 11/11 ناجح
- بناء `IDEXAL_ELECTRON_BUILD=1` (مسارات نسبية `./assets/...`) ثم تشغيل Electron مباشرة: التطبيق يفتح ويُعرض كاملًا (title: idexal Os)
- عبر CDP: لا نص sign-in/upgrade/subscription في: الواجهة، القائمة الجانبية، Library، كل تبويبات الإعدادات، منتقي النماذج، قائمة الحساب، لوحة الأوامر
- روابط Cloud settings المباشرة (`#/…/settings/cloud-account`) تُحوَّل إلى General
- تنفيذ مهمة فعلية: نموذج مجاني بدون حساب (Big Pickle) → ردّ "OK-LOCAL-TEST" بنجاح (لقطة: `%TEMP%\opencode\idexal-os-final.png`)
- ملاحظة: خدمة anonymous inference (Auto) غير متاحة من هذه الشبكة (`anonymous_unavailable`) فحُفظ النموذج الافتراضي على `opencode/big-pickle` (وهو `DEFAULT_MODEL` في المنتج نفسه) ليعمل أول استخدام مباشرة

## المراحل

- [x] Phase 0: git baseline snapshot
- [x] Phase 1: replacement script (dry-run → run) + file/dir renames
- [x] Phase 2: leftover scan & fixes (geometry greps clean except `_rebrand/` tooling + test)
- [x] Phase 3: display-name targeted pass (UI strings)
- [x] Phase 4: auth removal (targeted edits — public distribution requireSignin=false, requireActivation=false)
- [x] Phase 5: brand assets generation (icons/SVGs/favicons) + references — apply-svgs.py مكتمل
- [x] Phase 6: verification — typecheck نظيف + 11/11 اختبار + تشغيل كامل + مهمة حقيقية عبر CDP
- [x] Phase 7: final report + commit

## متابعات لاحقة (Follow-ups)

- سكربت التطوير على ويندوز: `pnpm --filter @idexal/desktop dev` يفشل (بادئة ENV بصيغة POSIX داخل cmd) — العمل الحالي يبني ثم يشغّل Electron مباشرة
- `pnpm den:contract` يحتاج MySQL محليًا (لم يُشغّل) — أي تغيير على مسارات Den لاحقًا يتطلب `pnpm dev:den:mysql`
- صور OG/social الثابتة (`idexal-os-social.png`) لم تُبنَ بعد
- خدمة Auto المجهولة تحتاج شبكة تصل إلى `os.idexal.com` (فشل TLS في هذه الشبكة: `ERR_SSL_VERSION_OR_CIPHER_MISMATCH`)
- ملفات scratch في `_rebrand/` (`_tmp_*.py`, `__pycache__`) غير مُتتبَّعة ويمكن حذفها

## ملاحظات قانونية

- ملفات LICENSE/LICENSES تحافظ على إشعارات حقوق النشر الأصلية (متطلب تراخيص OSS) مع تغيير اسم المنتج فقط — للمراجعة القانونية من المستخدم.
- Landing legal "DIFFERENT AI, INC." pending user legal decision.

## إضافة مزودين مخصصين (Custom providers)

- الطلب: داخل نافذة "Connect a provider" يُضاف خيار "Add a custom provider" لواجهات متوافقة مع OpenAI أو Anthropic: اسم، نوع الواجهة، رابط نقطة الاتصال، مفتاح اختياري، ومعرّفات النماذج (كل نموذج في سطر).
- `provider-auth/custom-provider-config.ts`: توليد معرّف `custom-<slug>` مع لاحقة رقمية عند التعارض (يُحسب `disabled_providers` ضمن المتعارضات)، وتحقّق من الرابط (http/https فقط)، وبناء كتلة المزود (`npm` + `options.baseURL` + `models`)، وتحديث `opencode.jsonc` احتياطيًا مع الحفاظ على التعليقات.
- `provider-auth/store.ts`: `addCustomProvider` يحفظ المفتاح في `auth.set` **قبل** كتابة الإعداد (جسر المحرك v2 يقرأ auth.json لحظة دفع المزود، فالترتيب العكسي كان يدفع المفتاح ناقصًا)، ثم يحدّث إعداد التشغيل عبر الخادم (`PATCH /workspace/:id/config`) أو `opencode.jsonc`، ثم `refreshProviders({ forceDispose: true })`.
- إصلاحان اكتُشفا أثناء الاختبار:
  - `resolvedView = selectedEntry ? view : "list"` كان يمنع عرض نموذج المزود المخصص (بلا مزود محدَّد) — أصبح `selectedEntry || view === "custom" ? view : "list"`.
  - محرك v2 يعلن live catalog فيتخطّى `refreshProviders` إعادة التحميل؛ أُضيف خيار `forceDispose` يُجبر `instance.dispose` بعد إضافة مزود ليظهر المزود ونماذجه فورًا.
- التحقق على البناء الحقيقي (CDP + خادم OpenAI محلي):
  - النموذج يُفتح، المزود يظهر فورًا في القائمة (4 مزودين) بعد الإضافة.
  - مهمة فعلية عبر `mock-model` أعادت الرد، وسجل الخادم أثبت `Authorization: Bearer fresh-key-789` للمفتاح المضاف حديثًا دون إعادة تشغيل.
  - مزود بلا مفتاح: الجسر يرسل ترويسة `Bearer idexal-os-engine-v2-preview-unset` الاحتياطية (نقاط النهاية المحلية تتجاهلها عادة).
- اختبارات الوحدة: `apps/app/tests/custom-provider-config.test.ts` — 12/12 ناجحة.
- لا إدخال في سجل الميزات: تطبيق سطح المكتب لا يقرأ `packages/features`، وقيود المؤسسات الحالية (`assertProviderAllowedByDesktopPolicy`) تبقى فعّالة.

### فحص الاتصال وجلب النماذج واختبارها

- زر **Check key & fetch models**: يطلب `GET {baseUrl}/models` عبر جسر العملية الرئيسية (`desktopFetchViaMain`) لتجاوز CORS؛ ترويسات OpenAI ‏(`Authorization: Bearer`) أو Anthropic ‏(`x-api-key` + `anthropic-version`). نجاح الطلب يعني أن المفتاح صالح.
- جلب تلقائي عند مغادرة حقل الرابط أو المفتاح (مرة واحدة لكل تركيبة رابط/مفتاح، مع حماية من التكرار)، مع بقاء الإدخال اليدوي ممكنًا إذا لم تدعم النقطة القائمة.
- الدمج يحافظ على المعرّفات المكتوبة يدويًا ويضيف الجديد فقط؛ الأخطاء تُعرض مع رمز HTTP ورسالة النقطة (مثل `HTTP 401 — Invalid API key provided`).
- زر **Test first model**: يرسل طلب إكمال صغيرًا (`max_tokens: 8`) إلى أول نموذج في القائمة — `POST /chat/completions` لواجهة OpenAI أو `POST /messages` لواجهة Anthropic — ويؤكد أن النموذج يرد فعليًا (مع مدة الاستجابة) أو يعرض سبب الفشل.
- وحدة `provider-auth/custom-provider-probe.ts` للطلبات، ودوال نقية في `custom-provider-config.ts` لتحليل قوائم النماذج (`data[]`/`models[]`/مصفوفة نصية) واستخراج رسائل الأخطاء.
- `SecretFieldHandle.read()` يقرأ المفتاح دون مسحه (لفحص الاتصال قبل الإرسال)، بينما يبقى `take()` عند الحفظ.
- إصلاح إضافي: زر Disconnect لمزود مخصص كان يزيل المفتاح فقط ويترك تعريف المزود. الآن `formatConfigWithoutCustomProvider` + `PATCH` بحذف الكتلة (`{provider:{id:null}}`) يزيلانها من إعداد التشغيل و`opencode.jsonc` معًا.
- التحقق (CDP + خادم وهمي): فحص بلا مفتاح أعاد 401 مع رسالة النقطة، ثم فحص بالمفتاح ملأ النموذجين تلقائيًا، واختبار النموذج الصحيح أعاد "answered in 134 ms"، ونموذج غير موجود أعاد 404، وDisconnect أزال المزود من القائمة ومن إعدادي التشغيل وv2.
- اختبارات الوحدة: 20/20 ناجحة (تشمل تحليل قوائم النماذج ورسائل الأخطاء وحذف كتلة المزود).
