PHPCS = phpcs --standard=PEAR --tab-width=4 --ignore=smarty,tpl_c -n

help:
	@echo "Use one of: all, clean, doc, test"

all:	clean doc

clean:
	@rm -f NDlite.html

doc:
	@./nd2html NDlite.php >NDlite.html

test:
	@$(PHPCS) NDlite.php
