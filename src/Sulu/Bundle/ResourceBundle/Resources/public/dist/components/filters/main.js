define(["suluresource/models/filter","app-config"],function(a,b){"use strict";var c="sulu.resource.filters.",d={baseFilterRoute:"resource/filters"},e=c+"new",f=c+"delete",g=c+"edit",h=c+"save",i=c+"list";return{initialize:function(){this.filter=null,this.bindCustomEvents(),"list"===this.options.display?this.renderList():"form"===this.options.display&&this.renderForm()},bindCustomEvents:function(){this.sandbox.on(e,function(){this.newFilter()}.bind(this)),this.sandbox.on(h,function(a){this.save(a)}.bind(this)),this.sandbox.on(f,function(a,b){"array"===this.sandbox.util.typeOf(a)?this.deleteFilters(a):this.deleteFilter(a,b)}.bind(this)),this.sandbox.on(g,function(a){this.load(a,b.getUser().locale)}.bind(this)),this.sandbox.on(i,function(a){this.sandbox.emit("sulu.router.navigate",d.baseFilterRoute+"/"+a)}.bind(this)),this.sandbox.on("sulu.header.language-changed",function(a){this.load(this.options.id,a.id)},this)},save:function(b){this.sandbox.emit("sulu.header.toolbar.item.loading","save"),this.filter=a.findOrCreate(b),this.filter.saveLocale(this.options.locale,{success:function(a){var c=a.toJSON();b.id?this.sandbox.emit("sulu.resource.filters.saved",c):this.load(c.id,this.options.locale)}.bind(this),error:function(){this.sandbox.logger.log("error while saving filter")}.bind(this)})},newFilter:function(){this.sandbox.emit("sulu.router.navigate","resource/filters/"+this.options.type+"/"+b.getUser().locale+"/add")},deleteFilter:function(b,c){return b||0==b?void this.showDeleteConfirmation(b,function(d){if(d){var e=a.findOrCreate({id:b});e.destroy({success:function(){this.sandbox.emit("sulu.router.navigate","resource/filters/"+c)}.bind(this)})}}.bind(this)):void this.sandbox.emit("sulu.overlay.show-error","sulu.overlay.delete-no-items")},deleteFilters:function(a){return a.length<1?void this.sandbox.emit("sulu.overlay.show-error","sulu.overlay.delete-no-items"):void this.showDeleteConfirmation(a,function(b,c){if(b){var d="/admin/api/filters?ids="+a.join(","),e=a.slice();this.sandbox.util.ajax({url:d,type:"DELETE",success:function(){e.forEach(function(a){this.sandbox.emit("husky.datagrid.record.remove",a)}.bind(this))}.bind(this),error:function(a){this.sandbox.logger.error("error when deleting multiple filters!",a)}.bind(this)})}}.bind(this))},showDeleteConfirmation:function(a,b){0!==a.length&&this.sandbox.emit("sulu.overlay.show-warning","sulu.overlay.be-careful","resource.filter.delete.warning",b.bind(this,!1),b)},load:function(a,b){this.sandbox.emit("sulu.router.navigate","resource/filters/"+this.options.type+"/"+b+"/edit:"+a+"/details")},renderForm:function(){var b=this.sandbox.dom.createElement('<div id="filters-form-container"/>'),c={name:"filters/components/form@suluresource",options:{el:b,locale:this.options.locale,type:this.options.type}};this.html(b),this.options.id?(this.filter=a.findOrCreate({id:this.options.id}),this.filter.fetchLocale(this.options.locale,{success:function(a){c.options.data=a.toJSON(),this.sandbox.start([c])}.bind(this)})):this.sandbox.start([c])},renderList:function(){var a=this.sandbox.dom.createElement('<div id="filters-list-container"/>');this.html(a),this.sandbox.start([{name:"filters/components/list@suluresource",options:{el:a,type:this.options.type}}])}}});