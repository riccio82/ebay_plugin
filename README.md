This folder contains code to customize MateCat that we don't
want to disclose to a public repository.

# Directories

## Plugins

Custom code goes here. This folder should be added to MateCat's
config.ini file in order for the class loader to find classes.

## test

This folder contains phpunit tests for related to customer features.
It makes use of the same test_helper.php file MateCat uses.

To run tests should be enough to run the command:

        ../../vendor/bin/phpunit

Since phpunit.xml is already configured to load support classes properly.
If any additional configuration is needed, please edit this folder's
phpunit.xml.



