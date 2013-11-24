Morbid Obesity
==============

###### A simple image board software based on kusaba.
Morbid Obesity is the code name for our development fork of KusabaX 9.3 with various changes for usability and speed.
Many deprecated functions have been updated, the way some things work can be different, but many files remain KusabaX.
This imageboard software runs best with php 5.3, apache, mysql and imagemagick. Good luck without imagemagick. 
If you are interested in getting to know our fork run some diffs and check it out! If you would like to report an issue feel free!
This is a community project from the love in our hearts, there are no paid developers, and as such things may be resolved slowly or not at all.


Development Process
===================

#### Development
We make use of gitflow which provides us with a fairly simple branching model with which to use git. 
Development on the master branch is to be avoided as much as possible. 
Instead, development is to be done on the dev server: dev.99chan.biz and issue branches that branch off of the development branch. 
Issue branches are to be named issXX where XX is the number of the issue you're currently working on.

Commit early and commit often. When working on an issue you should make as many commits as possible with notes about what has been changed in the file that you're eding.
Once the issue is resolved and working on the development server you can then merge the issXX branch into development.
If you intend to work on a new issue, create a new branch off of developemnt for that issue.
Otherwise you can merge development into master and deploy to production (dedi.99chan.biz).

#### Hotfixes
If something goes haywire and you have to work off of the master branch you should branch off of that as well.
Hotfix branches from the master branch will be named hfxXX where XX is the number of the issue the hotfix is intended to resolve.

#### Issues
Issues should be opened for every item that requires development.
Verbosity is valuable in issues, so please make sure to describe the issue you're working on in as much detail as possible.
Also take the time to describe the solution or any plans you have for the issue in as much detail as possible.
Development should not occur on any part of the system without a corresponding issue describing the nature of the problem and the implemented solution.
