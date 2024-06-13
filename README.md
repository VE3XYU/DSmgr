# DSmgr
Digital Signage Manager using MRSS

A really simple and free content management system for an MRSS feed.

I was looking for a free and open source content management system for digital signage but wasn't satisfied with anything. They all want you to pay a subscription for cloud storage, etc. and only a handful allow you to host yourself. Setup was needlessly complicated...I wanted something simple.

I have a Brightsign LS422 media player which the company EOLed. I determined that using the old BrightAuthor software I could point it to an MRSS feed once, and not need to worry about it again.

So, this project will be an MRSS content management system that will allow the user to very simply manage a feed and media.

Targeted Features:

1. Proper user authentication
2. Simple setup; get credentials and upload a template XML file that will act as the feed
3. Upload videos, upload images and set the duration they are shown on the screen.
4. Expire/remove old content
5. Manage the order in which the media are displayed
6. Set a schedule for each item (start/end date/time)
7. Preview mode to see the end result of the feed

Not Sure Yet:

1. My initial thought was that it would be a web app, but my goal is absolute simplicity.  Ideally, I wouldn't want the user to need any knowledge of web server config. Too many headaches, too many dependecies. I think it should be a compliled desktop app for the two good desktop operating systems, as well as Windows. Connection from desktop to server may just use SFTP. Interesting in using Qt, but I have no prior C++ experience.



