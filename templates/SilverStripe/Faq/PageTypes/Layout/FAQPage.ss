<h1>$Title</h1>
$Content
<% include FAQSearchForm %>
<div>
    <% loop $FilterFeaturedFAQs.sort('SortOrder') %>
        <% include FAQSearchResult %>
    <% end_loop %>
</div>
