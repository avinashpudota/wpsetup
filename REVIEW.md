# WordPress Quick Setup review

Reviewed version 2.6, commit `bcf68918101613a2c3566d89d39ff77b239216a1`.
The intended use is an administrator running setup once on a fresh WordPress
installation. This reduces the risk of deleting established content, but does
not remove failure-handling or retry issues.

## Implemented in 2.7

- Disable Atomic Editor after successful Elementor activation, for both fresh
  installations and existing installations.
- Persist `inactive` for `elementor_experiment-e_opt_in_v4` and
  `elementor_experiment-e_atomic_elements`. Where available, also follow the
  installed version's `Opt_In::OPT_OUT_FEATURES`, matching its settings UI.
  Containers and nested elements are not explicitly disabled.
- Return failure if Elementor activation or saving the opt-out fails.
- Check unchanged values correctly: `update_option()` returning false does
  not necessarily indicate failure.

Reference: [Elementor 4.2.4 opt-out implementation](https://github.com/elementor/elementor/blob/4.2.4/modules/atomic-widgets/opt-in/opt-in.php)
and [experiment option storage](https://github.com/elementor/elementor/blob/4.2.4/core/experiments/manager.php).
This is a setup-time default; administrators can subsequently enable Atomic Editor.

## Remaining findings

These are review findings, not fixes included in the Atomic Editor change.
Line numbers below refer to the reviewed 2.6 commit.

1. **High: failed setup is permanently marked complete and self-deleted.**
   `run_quick_setup()` continues after errors and unconditionally sets
   `quick_setup_completed`, deactivates, and unlinks itself (lines 112–130).
   A failed download or activation can therefore leave an incomplete site with
   no retry entry point. Retain the plugin and completion state until every
   required step succeeds, and show an accurate summary. Failed self-deletion
   is also not reported.

2. **Medium: page/menu creation cannot reliably resume.**
   `create_pages_and_menu()` puts all page creation inside the condition that
   Main Menu does not exist, and only adds newly created pages to the menu
   (lines 370–411). An existing menu skips page creation entirely; an existing
   Home page is neither assigned as the front page nor added to the menu.
   Menu/page creation errors are ignored. Reconcile pages, front-page options,
   and menu items independently and validate every returned ID/error.

3. **Medium: menu is assigned to the wrong Hello Elementor location.**
   Line 407 assigns `primary`; the installed Hello Elementor theme registers
   `menu-1` for its header and `menu-2` for its footer. Assign the registered
   header location. With the built-in header disabled this may not be visible
   immediately, but the theme location remains incorrect.

4. **Medium: other plugins' activation failures are treated as success.**
   Existing Pro Elements, Envato Elements, and QuickWebP branches ignore
   `activate_plugin()` errors (lines 260–264, 317–321, 339–343).
   Fresh Pro Elements also explicitly returns success on activation failure
   (lines 303–307). Validate activation before continuing dependent steps.
   The corresponding Elementor branches are fixed in 2.7.

5. **Medium, security hardening: raw error messages are rendered as HTML.**
   Line 113 concatenates `$result['message']` into the progress page. Some
   messages originate in download/extraction errors and WordPress filters.
   Escape these with `esc_html()` so external text cannot become executable
   markup. No unauthenticated exploitation was demonstrated.

6. **Medium, restricted/custom roles: capability gate is incomplete.**
   Only `install_plugins` is checked (line 24), although setup also changes
   site options, switches/deletes themes, publishes pages and permanently
   deletes content. Check the capabilities needed for these actions, or
   explicitly restrict this workflow to full site administrators. The setup
   URL does have a nonce check; anonymous visitors cannot simply run setup.

7. **Medium on reused sites: cleanup identifies content too broadly.**
   `cleanup_setup()` permanently deletes pages/posts by title and directly
   deletes comment ID 1 (lines 454–474). Matching titles/IDs do not prove the
   content is untouched WordPress sample content. This is less likely to hurt
   a genuinely fresh site; restrict cleanup to verified default fixtures and
   use WordPress deletion APIs for comments and cache/count maintenance.

Additional limitations: comment defaults do not remove comment support or
hide existing comments despite the inline descriptions; only published posts
have their comment status updated. The Dismiss button traverses one parent
too far and can hide the surrounding admin content instead of only its notice.
Pro Elements uses a moving GitHub master archive and manual directory moves,
making installs less reproducible and less compatible with non-direct
WordPress filesystem transports.

## Validation

- PHP 8.3 syntax validation passed.
- `php tests/atomic-editor.php`: 13 checks passed, covering fresh/existing
  installs, install/activation failures, option write failure, unchanged
  options, version-specific dependencies, and preservation of containers.
- Local WordPress with Elementor 4.2.4: temporarily enabled the five opt-out
  features, ran only the new configuration helper, then reloaded WordPress
  in another process. All five persisted as inactive and Elementor reported
  them inactive. Original settings were restored afterward.
- The full destructive setup/self-deletion flow was not run on the existing
  local site. This review does not claim all remaining findings are fixed.
