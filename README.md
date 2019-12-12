midnight/package-cohesion
=========================
This script checks how cohesive your Composer package is. It shows you file dependency clusters. If you've got a single cluster, your all good. Your package is very cohesive. If you've got one big cluster and several small ones, you should be fine. If you've got two or more larger clusters, you probably want to split your package into several smaller packages.

Inspired by the chapter on modules in Uncle Bob's "Clean Architecture".

How to run
----------
`php check.php path/to/your/project`

Why ist this just a script?
---------------------------
No .phar file? No Composer plugin? Just a script?

I just threw this together in a couple of hours. If there's interest, I'll work on it a little more. 
