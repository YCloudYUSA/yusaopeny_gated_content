# Welcome to Open Y Virtual Y smoke tests documentation

In order for Virtual Y app being tested in a short timeframe, please follow steps below

## Configuration

### User

Administrator

### Steps

1. Login as admin
2. Go to Virtual Y -> Virtual YMCA Settings -> Auth settings (/admin/openy/virtual-ymca/gc-auth-settings)
3. Verify there is ""Custom provider"" option in the list
4. Go to the Edit form 
5. Verify you can edit settings and they are saved correctly. 

### Expected Results

The form for custom authentication in Vitrual Y is working

## Check migration

### User

Administrator

### Steps

1. Login as admin
2. Go to Virtual Y -> Virtual YMCA Settings -> Auth settings (/admin/openy/virtual-ymca/gc-auth-settings)
3. Under Migration Settings find link to the form where you can upload CSV file 
4. Prepare CSV file with some test users based on the example https://github.com/fivejars/openy_gated_content/tree/master/modules/openy_gc_auth/modules/openy_gc_auth_custom#about
5. Upload CSV file 
6. Make sure upload was successful 
7. Under Migration Settings find a link to the form where you can run import
8. Verify the number of processed items is equal to number of records in the CSV
9. Go to People page (/admin/people)
10. Verify and confirm you can see users created during import. 
11. Verify users have role ""Virtual YMCA""
12. Verify all imported users should have status ""Blocked""

### Expected results

1. There is a form to upload CSV file before import 
2. There is a form to run/rollback import new users from CSV
3. After import new users created with the ""Blocked"" status. 

## Check form

### User

Anonymous

### Steps

1. Open Virtual Y landing page 
2. Verify you can see a block with a sign in form (onlye two fields email and captcha)
3. Verify you can enter email and access gated content

### Expected results

1. There is a login form on the landing page 
2. Login form works and give access to gated content

## Login with email verification

### User

Administrator / Anonymous

### Steps

1. Login as admin
2. Go to Virtual Y -> Virtual YMCA Settings -> Auth settings (/admin/openy/virtual-ymca/gc-auth-settings)
3. Choose ""Custom provider"" option in the list
4. Go to the Edit form 
5. Enable checkbox (if disabled) ""Enable Email verification""
6. Logout 
7. Go to Virtual Y landing page 
8. Enter email 
9. Verify you see a green message that verification link has been sent 
10. Open link from the received email 
11. Verify you got access to gated content 

### Expected results

1. Email verification settings provides the ability confirm email by sending a unique link that gives access to gated content 
2. User sess a message about sent email with instructions 
3. Link from the email opens gated content 
4. Email verification is needed only once

## Login without email verification 

### User

Administrator / Anonymous

### Steps

1. Login as admin
2. Go to Virtual Y -> Virtual YMCA Settings -> Auth settings (/admin/openy/virtual-ymca/gc-auth-settings)
3. Choose ""Custom provider"" option in the list
4. Go to the Edit form 
5. Disable checkbox (if enabled) ""Enable Email verification""
6. Logout 
7. Go to Virtual Y landing page 
8. Enter email 
9. Verify you got access to gated content 

### Expected results

After entering email user gets access to gated content

## Logs: date filters

### User

Administrator

### Steps

1. Login as admin
2. Go to Virtual Y logs (/admin/virtual-y-logs)
3. Click the "created" date filter fields (from and to)
4. Pick a date in each and apply the filters
5. Go to the activity report (/admin/virtual-y-logs/activity) and repeat steps 3-4 with "Date From" and "Date To"
6. Keep the browser console open during all steps

### Expected Results

Each date field opens the browser's date picker. After applying, the URL holds the dates as yyyy-mm-dd (for example `created_min=2026-10-01`, `changed_from=2026-10-01`) and the results are filtered. There are no JavaScript errors in the console (before the D12 fixes it showed `$(...).datepicker is not a function`).

## Logs: manual CSV export

### User

Administrator

### Steps

1. Login as admin
2. Go to Virtual Y logs export (/admin/virtual-y-logs/export)
3. Pick "Date Start" and "Date To" with the date picker
4. Click the export button

### Expected Results

The page opens (no "The website encountered an unexpected error"), both fields open the browser's date picker, and the export either downloads a CSV or reports that there is no data for the period. Check this on a site with the Trash module enabled too: the page used to return 500 there.

## Return to the requested page after login

### User

Anonymous

### Steps

1. Open a private browser window
2. Open the Virtual Y app with a deep link, for example `/virtual-ymca#/blog/123`
3. Open the browser developer tools, Application -> Cookies, and find `openy_gc_auth_destination`
4. Log in with the active provider

### Expected Results

Before login, the `openy_gc_auth_destination` cookie holds the hash exactly as typed (`#/blog/123`), path `/`, session cookie. After login the app opens that same page and the cookie is removed.

## Gated content direct pages

### User

Anonymous, then a user with the "view gated content entities pages" permission

### Steps

1. As anonymous, open a regular page (homepage, a branch, a landing page)
2. As anonymous, open the direct node page of a Virtual Y video, blog post, live stream or virtual meeting (`/node/<id>`)
3. Log in as a user with the "view gated content entities pages" permission and open the same direct page

### Expected Results

Regular pages open for everyone. The direct Virtual Y page returns access denied (403) for anonymous and opens for the user with the permission. No page returns a 500 error.

## Personal training (1on1 Meeting) series

### User

Administrator, then the series customer

### Steps

1. Login as admin
2. Go to 1on1 Meeting (/admin/virtual-y/personal_training) and add a "1on1 Meeting Series" with a few dates and a customer
3. Edit the series (change the time or the title) and save
4. Cancel the series
5. Delete the series
6. In another browser, log in to Virtual Y as the customer of a second series and open the personal training section

### Expected Results

Saving, cancelling and deleting a series each run a batch that reaches 100% without an error, and the series meetings are updated, cancelled or deleted accordingly. The customer sees their own meetings in the list.

## Shared content: import and downloads counter

### User

Administrator on a client site and on the shared content server

### Steps

1. On the client site, go to Shared content (/admin/virtual-y/shared-content/server) and open the fetch form of a server
2. Import a video and a blog post that use a category and an image
3. Import the same items again
4. On the server site, check the shares count of the imported items

### Expected Results

The import succeeds. Importing again does not create duplicate nodes, media or taxonomy terms. The server's shares count grows by one per import.

## SSO login (Daxko SSO, Reclique SSO)

### User

Virtual Y user of the configured SSO provider

### Steps

1. On a site where the provider is active, open the Virtual Y login and log in through the provider
2. Repeat the provider callback URL with a changed `state` value

### Expected Results

The normal login completes and opens Virtual Y. The tampered callback is rejected: Reclique redirects to the Virtual Y login with `error=invalid`, Daxko returns "Wrong cross site check".
