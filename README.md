# FAQ
[![Build Status](https://api.travis-ci.org/silverstripe/silverstripe-faq.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-faq)
[![Latest Stable Version](https://poser.pugx.org/silverstripe/silverstripe-faq/version.svg)](https://github.com/silverstripe/silverstripe-faq/releases)
[![Total Downloads](https://poser.pugx.org/silverstripe/silverstripe-faq/downloads.svg)](https://packagist.org/packages/silverstripe/silverstripe-faq)
[![License](https://poser.pugx.org/silverstripe/silverstripe-faq/license.svg)](https://github.com/silverstripe/silverstripe-faq/blob/master/LICENSE.md)
[![Dependency Status](https://www.versioneye.com/php/silverstripe:silverstripe-faq/badge.svg)](https://www.versioneye.com/php/silverstripe:silverstripe-faq)
[![Reference Status](https://www.versioneye.com/php/silverstripe:silverstripe-faq/reference_badge.svg)](https://www.versioneye.com/php/silverstripe:silverstripe-faq/references)

## Introduction

This module provides FAQ functionality on top of Solr.

Just by adding the module to the project, you'll get a ModelAdmin for FAQs, where you can manage Questions & Answers.
You only need to add an FAQPage type (comes with the module), and some questions and answers.

The module comes with its own Solr search index, customized to have a fuzzy search,
and has its own file for stopwords and synonyms.

## Requirements

 * [Fulltextsearch module](https://github.com/silverstripe-labs/silverstripe-fulltextsearch), v2.1.0 or up.
 * [Phockito](https://github.com/hafriedlander/silverstripe-phockito) and
 PHPunit for testing, not required if you don't care about running tests.

## Installation

Edit your project-wide composer.json file as follows; in the "require" block add:

    "silverstripe/faq": "*"

Then in the root of your project run:

    #> composer update silverstripe/faq

Or just

    composer install silverstripe/faq

Run a database rebuild by visiting *http://yoursite.com/dev/build*. This will add the required database
columns and tables for the module to function.
Remember to do `Solr_Configure` and `Solr_ReIndex`.

##### If you are using CWP

Check how to configure this module to behave like CWP-Solr [on the docs](docs/en/cwp.md).

## Quickstart

1. Add a FAQPage to your CMS

    ![](docs/images/faq-pagetype.png)

2. Add an FAQ on the FAQ ModelAdmin sidebar link

    ![](docs/images/faq-modeladmin.png)

3. Wait for Solr to reindex
4. Search your FAQ page :)

    ![](docs/images/faq-frontend.png)

## Configuration

### Frontend templates

All templates can be overloaded from within your project themes directory. Module default templates can be found in the module's `templates` directory.

### Configuration using YAML

FAQ module comes with a default set of templates to start the Solr index. If you want to override the path from where this files
come from, you need to add a YAML file like this.
```
---
Name: faqoverride
After: 'faq/*'
---
FAQSearchIndex:
  options:
    extraspath: 'new/path/to/extrapath'
    templatespath: 'new/path/to/template'

FAQ:
  question_boost: '3'
  answer_boost: '1'
  keywords_boost: '4'
```
### Adding Featured FAQs

Featured FAQs appear on the FAQ Page before a user performs a search. These can be added and organised from the `Featured FAQs` tab of the FAQ Page CMS admin.

![](docs/images/faq-featuredfaqsadmin.png)

- [Featured FAQs](docs/en/features.md)

### Categorizing

- [Categories](docs/en/features.md)

## Links

- [Module configuration](docs/en/configuration.md)
- [Configuring Solr](docs/en/configure-solr.md)
- [Using CWP](docs/en/cwp.md)

#### Search logging

FAQ searches performed on the FAQ page are logged so that a record is kept of the search term used and the results pages and FAQ articles that were viewed as a result of the search. There is also functionality to rate and leave a comment about an FAQ article.

All of this search data is available in the CMS in the "Search Log" section. CMS users can filter the data on numerous criteria and drill into results to find and rectify gaps in the knowledge base.

There are convenient links through to FAQ articles for content authors to amend the content and keywords for an article in order to boost the article higher in search results for appropriate search terms.

To connect all of this data together a session ID is associated with each record along with a search log ID passed as a GET param. This helps to prevent the data integrity being corrupted when URLs are shared between browsers and sessions. Further description of the functionality is described in the FAQ search logging unit test.

## TODO

- Get static config variables from yml files for controller
- Pagetypes, search index and dataobjects easily extendable
- version FAQs or move to inherit from SiteTree
- cwp manual configuration if not using `cwp-version`
- make this a supported SS module
- Better approach for archiving search logs and related data
