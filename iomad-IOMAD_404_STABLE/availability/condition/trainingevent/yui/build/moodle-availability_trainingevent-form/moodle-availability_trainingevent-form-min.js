YUI.add("moodle-availability_trainingevent-form",function(t,i){M.availability_trainingevent=M.availability_trainingevent||{},M.availability_trainingevent.form=t.Object(M.core_availability.plugin),M.availability_trainingevent.form.trainingevents=null,M.availability_trainingevent.form.initInner=function(i){this.trainingevents=i},M.availability_trainingevent.form.getNode=function(i){for(var a,e,l='<label><span class="pr-3">'+M.util.get_string("title","availability_trainingevent")+'</span> <span class="availability-trainingevent"><select name="id" class="custom-select"><option value="choose">'+M.util.get_string("choosedots","moodle")+'</option><option value="any">'+M.util.get_string("anytrainingevent","availability_trainingevent")+"</option>",o=0;o<this.trainingevents.length;o++)l+='<option value="'+(a=this.trainingevents[o]).id+'">'+a.name+"</option>";return e=t.Node.create('<span class="form-inline">'+(l+="</select></span></label>")+"</span>"),i.creating===undefined&&(i.id!==undefined&&e.one("select[name=id] > option[value="+i.id+"]")?e.one("select[name=id]").set("value",""+i.id):i.id===undefined&&e.one("select[name=id]").set("value","any")),M.availability_trainingevent.form.addedEvents||(M.availability_trainingevent.form.addedEvents=!0,t.one(".availability-field").delegate("change",function(){M.core_availability.form.update()},".availability_trainingevent select")),e},M.availability_trainingevent.form.fillValue=function(i,a){a=a.one("select[name=id]").get("value");"choose"===a?i.id="choose":"any"!==a&&(i.id=parseInt(a,10))},M.availability_trainingevent.form.fillErrors=function(i,a){var e={};this.fillValue(e,a),e.id&&"choose"===e.id&&i.push("availability_trainingevent:error_selecttrainingevent")}},"@VERSION@",{requires:["base","node","event","moodle-core_availability-form"]});