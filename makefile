.PHONY: default
default: main

media/css/moduleversion.css: media/scss/*.scss
	sass $^ $@

media/css/moduleversion.min.css: media/scss/*.scss
	sass -s compressed $^ $@

moduleversion.zip: moduleversion.php moduleversion.xml README.md script.php media/css/*.css media/** src/** language/**
	zip -rq9 $@ $^

main: moduleversion.zip
