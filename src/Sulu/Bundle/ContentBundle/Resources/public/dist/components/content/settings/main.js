define(["app-config"],function(a){"use strict";var b=1,c=2,d=4;return{view:!0,layout:{changeNothing:!0},templates:["/admin/content/template/content/settings"],initialize:function(){this.sandbox.emit("husky.toolbar.header.item.disable","template",!1),this.load(),this.bindCustomEvents()},bindCustomEvents:function(){this.sandbox.on("sulu.header.toolbar.save",function(){this.submit()},this),this.sandbox.on("sulu.content.contents.saved",function(){window.location.reload()},this);var a=function(){this.sandbox.emit("sulu.content.contents.set-header-bar",!1)}.bind(this);this.sandbox.on("husky.select.nav-contexts.selected.item",a.bind(this)),this.sandbox.on("husky.select.nav-contexts.deselected.item",a.bind(this))},load:function(){this.sandbox.emit("sulu.content.contents.get-data",function(a){this.render(a)}.bind(this))},render:function(a){this.data=a,this.html(this.renderTemplate("/admin/content/template/content/settings")),this.setData(a),this.listenForChange()},setData:function(a){var c=parseInt(a.nodeType);c===b?this.sandbox.dom.attr("#content-node-type","checked",!0):c===d?this.sandbox.dom.attr("#internal-link-node-type","checked",!0):c===d&&this.sandbox.dom.attr("#external-link-node-type","checked",!0),this.sandbox.on("husky.select.nav-contexts.initialize",function(){this.sandbox.dom.data("#nav-contexts","selection",a.navContexts),this.sandbox.dom.data("#nav-contexts","selectionValues",a.navContexts),$("#nav-contexts").trigger("data-changed")}.bind(this))},listenForChange:function(){this.sandbox.dom.on(this.$el,"keyup change",function(){this.setHeaderBar(!1)}.bind(this),".trigger-save-button")},setHeaderBar:function(a){this.sandbox.emit("sulu.content.contents.set-header-bar",a)},submit:function(){this.sandbox.logger.log("save Model");var b={};b.navContexts=this.sandbox.dom.data("#nav-contexts","selection"),b.nodeType=parseInt(this.sandbox.dom.val('input[name="nodeType"]:checked')),this.data=this.sandbox.util.extend(!0,{},this.data,b),this.sandbox.emit("sulu.content.contents.save",this.data,b.nodeType===c?"internal-link":b.nodeType===d?"external-link":a.getSection("sulu-content").defaultTemplate)}}});