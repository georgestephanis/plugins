=== Ndizi Project Management ===
Contributors: georgestephanis
Donate link: http://www.charitywater.org/donate/
Tags: Client Management, Project Management, Task Management, Time Tracking, Invoicing
Requires at least: 2.7
Tested up to: 3.2.1
Stable tag: 0.9.7.0

Ndizi Project Management is a Project Management solution, akin to offerings like Basecamp. But it's free and runs under WordPress.

== Description ==

Ndizi Project Management is in semi-active development, meaning simply that as I have time between client projects, I'll be developing it further.

Also, as it is beta software, please use the 'Bug Report' in the plugin if you see anything break!  If I'm alerted, odds are it'll be patched shortly.

Currently, Ndizi supports storing:

* Clients
* Projects (which belong to clients)
* Tasks (which belong to Projects and can be assigned to a WordPress User)
* Time Entries (which belong to Projects and can be assigned to a WordPress User) 
* Invoices (which belong to Projects, and [soon] can be assigned time reports)
* Messages and File Attachments, (which will shortly be) attachable to Projects and Tasks

Ndizi also lets you select a front-end page of your site, where your clients can authenticate and view their details, invoices, projects (including time totals for each, but not the individual time reports), and each task (with status) assigned to their projects.  They can also add new tasks, which are then added to the back end for you to modify, clarify, and assign as-needed.

Coming Features (in no particular order):

* Linking time reports to invoices, or indicating that they are 'non-billable' hours.
* Optionally linking time reports to a given `task`.
* Exporting invoices to other services such as FreshBooks and other invoicing systems, rather than managing internally.
* Allowing users and clients to post messages and upload files, attaching them to projects or tasks.  (Structure is in-place currently, but not fully implemented)
* New `Contacts` data type, enabling you to associate (none, one, or more) contacts with (none, one, or more) clients or projects.
* E-mail sent to specified users when clients add tasks through the front-end of the site.
* New Time Entry pages for non-administrative users.
* Adding more in-depth user permissions for assorted tasks.
* Adding 'reports' page for users to see stats, time totals across projects and such.
* Setting Clients and Projects to 'inactive' or 'archiving' old ones. (Half-implemented.  Now I just need to have 'inactive' mean something)
* Gantt Charts, due to massive popular demand.
* Open to any other User Interface suggestions!

If you like Ndizi and want to make a donation, don't give it to me.  Give it to people that need it.  http://www.charitywater.org/donate/  (I'm not associated with them in any way, I just happen to think they do good work)

== Installation ==

1. Upload the folder `ndizi-project-management` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Add your clients, projects, tasks, and time.
1. Give clients their key, if you like, and they can view their invoices, projects, and tasks on your front-end `Ndizi` page, and even add new tasks.

== Frequently Asked Questions ==

= Another project management system?  Really? =

Yup.  I got bored, what can I say?  And I figured the fact that I couldn't find a particularly good system anywhere for WordPress meant that people are just hankering for a fix.

This is free software, and will always remain free software.  I'm actively expanding its capabilities, and will be rolling in Invoicing before we actually hit version 1.0

= So what does 'Ndizi' mean, anyways? =

Ndizi is the swahili word for banana.  Apart from being an awesome source of potassium, loved by minions ( http://youtu.be/BYBw_o_2nG0 ), and curing hangovers, Bananas are sung about by Harry Belafonte in his "Day-O (The Banana Boat Song)" in which he calls "Come, mister tally man, tally me banana ..."  Like Harry, we all need our bananas tallied, so that we can collect our wage.  Hopefully Ndizi helps you to get this done, and get on with your life.

== Changelog ==

= 0.9.7.0 =
* Minor bugfix, client sites are now properly displaying on invoices.

= 0.9.6.9 =
* Minor bugfixes, changing some db data types for backward compatability with previous versions of MySQL.
* Removing old option for displaying time for in admin header -- which is no longer used in WP 3.2
* Correcting a mis-typed array key for time calculations.
* Added functionality for admin to manually set client access keys without permitting duplicates.
* Added link on clients page for admin to email access key directly to client.
* Remedied random mis-name of a variable where on the front-end it would never display the client's website.

= 0.9.6.8 =
* Minor bugfixes, changing some db data types for backward compatability with previous versions of MySQL.
* Removing old option for displaying time for in admin header -- which is no longer used in WP 3.2
* Correcting a mis-typed array key for time calculations.
* Added functionality for admin to manually set client access keys without permitting duplicates.
* Added link on clients page for admin to email access key directly to client.

= 0.9.6.7 =
* Minor bugfix, had $ndizi instead of $this inside the class!

= 0.9.6.6 =
* Minor bugfix, comments not getting commented out, etc.

= 0.9.6.5 =
* Adding "Time Entry" form to back-end for non-admin users.  Tentatively allows them to add time to any project, and view/edit all their past times.  Future releases will feature custom permissions assignable on a user-by-user basis.
* As per a user request, I've added in a "Client Log-In" widget!  So go ahead and put 'em in your sidebars!
* Some minor JS tweaks ... implementing a Timepicker in some areas to get feedback.  If it doesn't work right for you, and is displaying 'NaN', upgrade to WordPress 3.1 ~~ older versions have an outdated copy of jQuery UI.

= 0.9.6.4 =
* Minor bugfix, I mistypo-ed on the linking-user-to-task fields.
* Minor tweak on email bug reports ... adding in a reply-to header, back to the submitted.  To make my life easier!

= 0.9.6.3 =
* Minor bugfix, missed a parameter in a get_tasks() function call, so it was accidentally displaying all of them!

= 0.9.6.2 =
* Minor bugfix, missed a parameter in a get_times() function call, so it was accidentally displaying all of them!

= 0.9.6.1 =
* Minor bugfix, you probably didn't even notice it!

= 0.9.6 =
* Big changes.  Added structures for Permissions table and Attachments table.  Permissions is not yet implemented, and attachments is half-implemented.  Other half coming soon!  I just need to sort out layouts and interface structures for how to make it work.
* Added 'active' column to clients and projects.  Just displays for now, but soon should give you the option to filter them out by default.
* Migrated many functions away to using $args parameter instead of passing values as such.
* Abstractions.  Lots and lots of abstractions.
* Added front-end widget.

= 0.9.5.9 =
* Quick bugfix, session checking for logged in clients accidentally was eating the content of every other page as well.  Now it'll only eat the proper page.

= 0.9.5.8 =
* Quick bugfix, I accidentally left a session_start() down in a filter, which was causing warnings for some folks.  Bumped up to init, all good now.

= 0.9.5.7 =
* Quick bugfix, I tried running two functions as though they were properties.  `$this->make_tables;` != `this->make_tables();`

= 0.9.5.6 =
* Lots of shinies, but mostly behind the scenes.
* Added in a `terms` column to the Invoices table.
* Invoices now display on a client's page ONLY if they are set to something higher than 'draft'.
* Added some base CSS formatting to the front-end client display page.
* Internationalization support!  I took the initiative and put the `__()` and `_e()` functions in place.  Any volunteers to translate it?

= 0.9.5.5 =
* Quick bugfix, it seems that the plugin didn't actually install the invoice table unless it was turned on for the first time ... now it checks on each init to see whether its tables are there.  If they are, it doesn't do anything.  If not, it makes them.  I'll change this in a future version to be a bit more efficient, but for now this should hold steady.

= 0.9.5.4 =
* Rolled the last of the external page files directly into the class.  Should make for easier management while the plugin is under active development.  They may be repartitioned out again in time.
* Added admin page option to display or hide header time reporting.
* Tidied up menu and naming.

= 0.9.5.3 =
* Invoicing!  It's in, and it's ... well ... there.  More to come in the next release!  (I promise!)
* Minor tweak to front-end display, putting 'description' in its own line under each entry.
* Temporarily hiding Admin Header time entry.  It'll be returnable by a toggle in the next release (but was doing evil things to smaller width windows)

= 0.9.5.2 =
* Added admin header form (top-right) for easier time entries.
* Added admin dashboard widget to display current Ndizi status.  This will soon replace the 'dashboard' page in Ndizi.
* Rolled most of the pages into the class, lessening the amount of external files.
* Started sketching out permissions functions.

= 0.9.5.1 =
* Fixed problem when clients create new tasks, it seems the 'name' field being passed by post on the front end caused problems.  Fixed.
* Changed front-end page behavior so that it -will- display page content when client is not logged in, followed by login fields.  Once logged in, however, it does not display page content.
* Fixed errant ifcheck that wouldn't authorize a client verification unless the user was already logged in to the back-end.

= 0.9.5 =
* Initial upload to WordPress.org
* Added front-end client page, displaying all their projects and a form for them to request new tasks.
* Tidied up a few of the SQL queries, and data type to data type linkages.

== Upgrade Notice ==

= 0.9.6.6 =
* Good to go!  Cookies for all!

= 0.9.6.6 =
* Bug fix ... for some reason Comments were not being commented out.

= 0.9.6.5 =
* Go on ahead and upgrade!  Everything should work properly, and I'm doing some progressive enhancement with WordPress 3.1 bundled jQuery UI versions.  So if you're getting some JS errors, upgrade your WP!

= 0.9.6.4 =
* In the words of Nike ... just do it.

= 0.9.6.3 =
* None

= 0.9.6.2 =
* None

= 0.9.6.1 =
* None

= 0.9.6 =
* Phew.  Big upgrade from a back-end perspective, not so much from a front-end.
* Added new tables, and new columns to existing tables.
* Don't think I broke anything, but if you're on a critical system, wait a couple days for people to vet the system.

= 0.9.5.9 =
* Recommended update, fixes glitch with page display for logged in clients.

= 0.9.5.8 =
* Optional update, fixes minor glitch where a warning could display (depending on your reporting levels) on some page-loads of front-end page.

= 0.9.5.7 =
* Found the tablemaker problems, think it's fixed.

= 0.9.5.6 =
* Big upgrade!  Tidied up a lot of internals, added a `terms` column to the invoices table, not much else major from an upgrade standpoint.
* Invoices, once published, will display on a client's front-end logged-in page.  Drafts will not.

= 0.9.5.5 =
* Fixed tablemaker function to check on init.  Bugfix, not much to worry about.

= 0.9.5.4 =
* De nada.

= 0.9.5.3 =
* New table introduced to DB!  Should be automatically handled by Upgrade mechanism for WordPress.

= 0.9.5.2 =
* Just bugfixes and minor loose ends.  Nothing problematic.

= 0.9.5.1 =
* Just bugfixes and tying up some loose ends.  Upgrade away!

= 0.9.5 =
* Not Applicable, initial public release.

