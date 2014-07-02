# Thankful People plugin for Vanilla Forums 2.x

####1.2.0.140702
* Implemented Ajax functions to send the "thanks".
* Added fields and methods to retrieve the amount of thanks received by Discussions, Comments and Users.
* Added extended DiscussionModel, CommentModel and UserModel.

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
