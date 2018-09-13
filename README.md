easyengine/cron-command
=======================

Manages cron jobs in EasyEngine



Quick links: [Using](#using) | [Contributing](#contributing) | [Support](#support)

## Using

This package implements the following commands:

### ee cron add

Adds a cron job to run a command at specific interval etc.

~~~
ee cron add [<site-name>] --command=<command> --schedule=<schedule>
~~~

**OPTIONS**

	[<site-name>]
		Name of site to run cron on.

	--command=<command>
		Command to schedule.

	--schedule=<schedule>
		Time to schedule. Format is same as Linux cron.

We also have helper to easily specify scheduling format:

 Entry                  | Description                                | Equivalent To
 -----                  | -----------                                | -------------
 @yearly (or @annually) | Run once a year, midnight, Jan. 1st        | 0 0 1 1 *
 @monthly               | Run once a month, midnight, first of month | 0 0 1 * *
 @weekly                | Run once a week, midnight between Sat/Sun  | 0 0 * * 0
 @daily (or @midnight)  | Run once a day, midnight                   | 0 0 * * *
 @hourly                | Run once an hour, beginning of hour        | 0 * * * *


### ee cron delete

Deletes a cron job

~~~
ee cron delete <cron-id>
~~~

**OPTIONS**

	<cron-id>
		ID of cron to be deleted.

**EXAMPLES**

    # Deletes a cron jobs
    $ ee cron delete 1



### ee cron list

Lists scheduled cron jobs.

~~~
ee cron list [<site-name>] [--all]
~~~

**OPTIONS**

	[<site-name>]
		Name of site whose cron will be displayed.

	[--all]
		View all cron jobs.

**EXAMPLES**

    # Lists all scheduled cron jobs
    $ ee cron list

    # Lists all scheduled cron jobs of a site
    $ ee cron list example.com



### ee cron run-now

Runs a cron job

~~~
ee cron run-now <cron-id>
~~~

**OPTIONS**

	<cron-id>
		ID of cron to run.

**EXAMPLES**

    # Runs a cron job
    $ ee cron run-now 1

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.


### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/easyengine/cron-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/easyengine/cron-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/easyengine/cron-command/issues/new) to discuss whether the feature is a good fit for the project.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://easyengine.io/support/


*This README.md is generated dynamically from the project's codebase using `ee scaffold package-readme` ([doc](https://github.com/easyengine/scaffold-package-command)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
