#Thankful People plugin for Vanilla Forums 2.x

##Description
Thankful People plugin implements adds a "thanks" feature to your forum. Users can click on a "Thanks" button to thank each other on  posts, comments and virtually any other entity.

##Credits
Plugin was commissioned by [ThankFrank.com](http://thankfrank.com), an online community dedicated to provide genuine and honest help to online shoppers.

**Important**: this plugin was initially based on [Thankful People by Jerl Liandri](http://vanillaforums.org/addon/thankfulpeople-plugin), but has been heavily redesigned for use by [ThankFrank.com](http://thankfrank.com). While most of its features work on the same way, it's not compatible out of the box with the original plugin. If you wish to use move from the old plugin to the new one, please take a backup of your database first, then install the new plugin and migrate the old data to the new format.

##Installation
* Copy the ThankfulPeople folder in the `<your forum folder>/plugins` folder.
* Delete all .ini files from the cache folder and all its subfolders. Cache folder is also in your Vanilla installation folder.
* Enable the Thankful People plugin.

##Configuration
The plugin can read a few parameters from the configuration file:

* **Plugins.ThankfulPeople.AllowedObjectTypes**
	When set, thanks can be sent only for the specified object types. Defaults to **all**.
**Example**: `$Configuration['Plugins']['ThankfulPeople']['AllowedObjectTypes'] = array('discussion', 'comment', 'question');`
* **Plugins.ThankfulPeople.DisallowedObjectTypes**
	When set, thanks can **not** be sent for the specified object types. This parameter takes precedence over the `AllowedObjectTypes` list. Defaults to **none**.
**Example**: `$Configuration['Plugins']['ThankfulPeople']['DisallowedObjectTypes'] = array('discussion', 'comment', 'question');`
* **Plugins.ThankfulPeople.AllowRevoke**
	Specifies if thanks can be revoked, once given. Defaults to **false**.
**Example**: `$Configuration['Plugins']['ThankfulPeople']['AllowRevoke'] = true;`

##Requirements
* PHP 5.3+
* Vanilla Forums 2.0.10 or later (see notes)
* [Aelia Foundation Classes](https://businessdad@bitbucket.org/businessdad/aelia-foundation-classes.git) 14.06.24.001 or later

Notes
* Compatibility with Vanilla Forums 2.1.x is work in progress.

##License
GPLv3 (http://www.gnu.org/licenses/gpl-3.0.txt
