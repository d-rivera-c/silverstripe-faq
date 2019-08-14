<select name="$SearchCategoryKey" style="width:200px">
    <option value="">$CategoriesSelectAllText</option>
    <% loop $SelectorCategories %>
        <option value="$ID" <% if $Selected %>selected="selected"<% end_if %>>$Name</option>
    <% end_loop %>
</select>