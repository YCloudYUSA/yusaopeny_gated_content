## About
The goal of this module is to give ability
to log into Vitrual Y using OAuth2 protocol, in case if your CRM is Reclique.

## How to use this integration.

1. Enable this module.
2. Setup your Reclique SSO OAuth2 credentials
here: /admin/openy/virtual-ymca/gc-auth-settings/provider/reclique_sso
3. Set Reclique SSO OAuth2 as your main authorization plugin
at the Virtual YMCA settings: /admin/openy/openy-gc-auth/settings

## I need help.
In case, if you need help, please write your question
at the #developers channel at Open Y slack.

## Notice about the email change
If the email was changed on the Reclique SSO provider side, the user should
be logged in with a new email after the change. No additional actions should
be needed from the Drupal site admin side.
When the issue was taken by QA -- it was not possible to check this scenario
since there were no ability to change the email by user himself on the
Reclique SSO provider site. So for the moment this feature is marked as
"untested".
