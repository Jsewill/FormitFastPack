﻿
<!-- text -->
  <input type="[[+type]]" name="[[+name]]" id="[[+name]]" value="[[+current_value]]" class="[[+class]][[+error_class]]" size="40">
<!-- text -->

<!-- textarea -->
  <textarea id="[[+name]]" class="[[+type]] [[+class]][[+error_class]]" name="[[+name]]">[[+current_value]]</textarea>
<!-- textarea -->

<!-- checkbox -->
<span class="boolWrap">
<input name="[[+name]]" type="hidden" value="[[+default]]" />
[[+options_html]]
</span>
<!-- checkbox -->

<!-- radio -->
<span class="boolWrap">
<input type="hidden" name="[[+name]]" value="[[+default]]" />
[[+options_html]]
</span>
<!-- radio -->

<!-- submit -->
<input id="[[+name]]" class="button" name="[[+name]]" type="[[+type]]" value="[[+message:default=`Submit`]]" />
<a href="[[~[[*id]]]]" class="button">Cancel</a>
<!-- submit -->

<!-- select -->
<select name="[[+name]]" id="[[+name]]" class="[[+class]]"[[+multiple:notempty=` multiple="multiple"`]]>
  [[+header:notempty=`<option name="[[+name]]" value="[[+default]]">[[+header]]</option>`]]
  [[+options_html]]
</select>
<!-- select -->

<!-- static -->
<span class="static_field">[[!+[[+name]]]]</span>
<!-- static -->

<!-- option --><option value="[[+value]]" [[!+[[+prefix]][[+name]]:FormItIsSelected=`[[+value]]`]]>[[+label]]</option><!-- option -->

<!-- bool --><span class="boolDiv [[+class]]">
  <input type="[[+type]]" class="[[+type]]" value="[[+value]]" name="[[+name]][[+array:notempty=`[]`]]" id="[[+name]][[+value:md5]]" [[!+[[+prefix]][[+name]]:FormitIsChecked=`[[+value]]`]] /> 
<label for="[[+name]][[+value:md5]]" class="[[+type]]" id="label[[+name]][[+idx]]">[[+label]]</label></span><!-- bool -->
