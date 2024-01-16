/*! third party licenses: js/vendor.LICENSE.txt */
import{V as n,t as m,f as d}from"./index-GsrzU7tr.mjs";import{S as o,m as s,a as c,A as p,V as u,s as a}from"./settings-store-niU1YCeu.mjs";import{c as f}from"./NcCheckboxRadioSwitch-6yqOMLng.mjs";import{n as l}from"./index.es-C_jZ0iWl.mjs";import"./index-Hxwv_8S9.mjs";import"./logger-c02nfst_.mjs";const g={name:"AdminSettings",components:{NcCheckboxRadioSwitch:f,NcSettingsSection:o},computed:{...s({emailEnabled:"emailEnabled"}),settingDescription(){return this.emailEnabled?t("activity","Choose for which activities you want to get an email or push notification."):t("activity","Choose for which activities you want to get a push notification.")}},mounted(){this.setEndpoint({endpoint:"/apps/activity/settings/admin"})},methods:{...c(["setEndpoint","toggleEmailEnabled"])}};var h=function(){var i=this,e=i._self._c;return e("NcSettingsSection",{attrs:{name:i.t("activity","Notification")}},[e("NcCheckboxRadioSwitch",{attrs:{type:"checkbox",checked:i.emailEnabled},on:{"update:checked":function(r){return i.toggleEmailEnabled({emailEnabled:r})}}},[i._v(" "+i._s(i.t("activity","Enable notification emails"))+" ")])],1)},v=[],y=l(g,h,v,!1,null,null,null,null);const E=y.exports,S={name:"DefaultActivitySettings",components:{ActivityGrid:p,NcSettingsSection:o},computed:{...s({emailEnabled:"emailEnabled"})},mounted(){this.setEndpoint({endpoint:"/apps/activity/settings/admin"})},methods:{...c(["setEndpoint","toggleEmailEnabled"])}};var b=function(){var i=this,e=i._self._c;return e("NcSettingsSection",{attrs:{name:i.t("activity","Default settings"),description:i.t("activity","Configure the default notification settings for new accounts.")}},[e("ActivityGrid")],1)},w=[],A=l(S,b,w,!1,null,null,null,null);const N=A.exports;n.prototype.t=m,n.prototype.n=d,n.use(u),new n({el:"#activity-admin-settings",store:a,name:"ActivityPersonalSettings",render:i=>i(E)}),new n({el:"#activity-default-settings",store:a,name:"ActivityDefaultSettings",render:i=>i(N)});
