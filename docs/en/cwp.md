# Using CWP

If your using the module with CWP, extra requirements should be noticed (even if not required on composer for this project)

 * CWP 1.1.1
 * CWP-core 1.1.2

Also notice that some custom search configurations from CWP don't apply to FAQs out of the box.
Manual configuration will need to be applied to make it resemble CWP conf.

#### Using the main branch

If you want to use the master branch, but want the Solr configuration that comes out of the box with CWP, you need to make
some adjustments.

First follow the installation instructions, and configure the module as you like. Everything should work without
making any adjustments, the only thing working different is going to be the suggestions displayed ("Did you mean?"
feature).

You need to add this code to your `config.yml`

```yaml
FAQSearchIndex:
  copy_fields:
    - _text
    - _spellcheckText
```

Then run a `flush=1`.

Now change `solrconfig.xml` in the `conf` folder (one comes with the module, but if you are going to modify the file,
copy the `conf` folder into your project and change the path as noted in the configuration instructions) from this

```xml
<lst name="spellchecker">
    <str name="name">default</str>
    <str name="field">_text</str>
```

to

```xml
<lst name="spellchecker">
    <str name="name">default</str>
    <str name="field">_spellcheckText</str>
```

And in the same folder (`conf`), locate `schema.ss` and add

```xml
<field name='_spellcheckText' type='textSpellHtml' indexed='true' stored='false' multiValued='true' />
```

It will end with something like this

```xml
<fields>
    $FieldDefinitions

    <field name='_spellcheckText' type='textSpellHtml' indexed='true' stored='false' multiValued='true' />
    <field name="_version_" type="long" indexed="true" stored="true" multiValued="false"/>
</fields>
```

Do `Solr_Configure` and `Solr_Reindex`, and you should have FAQ module suggestion feature working like CWP.
