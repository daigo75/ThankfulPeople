# Thankful People plugin for Vanilla Forums 2.x

####1.2.4.140708
* Improved permission checking on sending thanks:
	* MessageThanksModule now always displays the thanks received by an object.
	* Permission checks are now more fine grained, checking if a user already thanked for an object and if the object is his own.
	* Strenghened permission checks in ThankfulPeoplePlugin::Controller_GiveThanks().
	* Ensured that Thanks are always visible to anyone, including guests. Ref. https://app.asana.com/0/8904858729142/14493217456788

####1.2.3.140704
* Fixed reference to ThanksLogModel in main plugin class.

####1.2.2.140704
* Removed unneeded permission check.

####1.2.1.140703
* Corrected references to language definitions.
* Corrected display of Message Thanks module:
	* Module is now displaying the number of thanks received by a discussion/comment when its owner is looking at it.

####1.2.0.140702
* Implemented Ajax functions to send the "thanks".
* Added fields and methods to retrieve the amount of thanks received by Discussions, Comments and Users.
* Added extended DiscussionModel, CommentModel and UserModel.
* Added optional auto-rendering of elements, such as modules.

####1.1.0.140701
* Redesigned main plugin functions:
	* Replaced "ThankFor" method with "GiveThanks" method.
	* Replaced GET commands with POSTed forms.
	* Removed "UnthankFor" (to be replaced with an equivalent method).
	* Updated README.
	* Added change log.

####1.0.0.140629
* Refactored original plugin by Jerl Liandri:
	* Added dependency from Aelia Foundation Classes.
	* Added logging of common errors.
	* Redesigned ThanksLog model.
	* Altered structure of ThanksLog table and replaced its engine with InnoDB.
	* Added transactions for critical operations.
