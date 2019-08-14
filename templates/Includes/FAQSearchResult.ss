<div>
    <h1><a href="$Link">$Question</a></h1>
    <p>
        $Answer.RichLinks.LimitCharacters(80)
    </p>
    <% if $Category %><div>Category: <em>$Category.Name</em></div><% end_if %>
    <a href="$Link">$Top.MoreLinkText</a>
</div>