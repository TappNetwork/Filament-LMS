(()=>{function se(n,e){var t=Object.keys(n);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(n);e&&(r=r.filter(function(i){return Object.getOwnPropertyDescriptor(n,i).enumerable})),t.push.apply(t,r)}return t}function ce(n){for(var e=1;e<arguments.length;e++){var t=arguments[e]!=null?arguments[e]:{};e%2?se(Object(t),!0).forEach(function(r){X(n,r,t[r])}):Object.getOwnPropertyDescriptors?Object.defineProperties(n,Object.getOwnPropertyDescriptors(t)):se(Object(t)).forEach(function(r){Object.defineProperty(n,r,Object.getOwnPropertyDescriptor(t,r))})}return n}function R(){R=function(){return n};var n={},e=Object.prototype,t=e.hasOwnProperty,r=Object.defineProperty||function(l,s,f){l[s]=f.value},i=typeof Symbol=="function"?Symbol:{},a=i.iterator||"@@iterator",o=i.asyncIterator||"@@asyncIterator",c=i.toStringTag||"@@toStringTag";function d(l,s,f){return Object.defineProperty(l,s,{value:f,enumerable:!0,configurable:!0,writable:!0}),l[s]}try{d({},"")}catch{d=function(s,f,E){return s[f]=E}}function u(l,s,f,E){var g=s&&s.prototype instanceof T?s:T,S=Object.create(g.prototype),M=new F(E||[]);return r(S,"_invoke",{value:w(l,f,M)}),S}function k(l,s,f){try{return{type:"normal",arg:l.call(s,f)}}catch(E){return{type:"throw",arg:E}}}n.wrap=u;var b={};function T(){}function P(){}function y(){}var N={};d(N,a,function(){return this});var j=Object.getPrototypeOf,A=j&&j(j(V([])));A&&A!==e&&t.call(A,a)&&(N=A);var v=y.prototype=T.prototype=Object.create(N);function h(l){["next","throw","return"].forEach(function(s){d(l,s,function(f){return this._invoke(s,f)})})}function p(l,s){function f(g,S,M,I){var L=k(l[g],l,S);if(L.type!=="throw"){var x=L.arg,B=x.value;return B&&typeof B=="object"&&t.call(B,"__await")?s.resolve(B.__await).then(function($){f("next",$,M,I)},function($){f("throw",$,M,I)}):s.resolve(B).then(function($){x.value=$,M(x)},function($){return f("throw",$,M,I)})}I(L.arg)}var E;r(this,"_invoke",{value:function(g,S){function M(){return new s(function(I,L){f(g,S,I,L)})}return E=E?E.then(M,M):M()}})}function w(l,s,f){var E="suspendedStart";return function(g,S){if(E==="executing")throw new Error("Generator is already running");if(E==="completed"){if(g==="throw")throw S;return ne()}for(f.method=g,f.arg=S;;){var M=f.delegate;if(M){var I=O(M,f);if(I){if(I===b)continue;return I}}if(f.method==="next")f.sent=f._sent=f.arg;else if(f.method==="throw"){if(E==="suspendedStart")throw E="completed",f.arg;f.dispatchException(f.arg)}else f.method==="return"&&f.abrupt("return",f.arg);E="executing";var L=k(l,s,f);if(L.type==="normal"){if(E=f.done?"completed":"suspendedYield",L.arg===b)continue;return{value:L.arg,done:f.done}}L.type==="throw"&&(E="completed",f.method="throw",f.arg=L.arg)}}}function O(l,s){var f=s.method,E=l.iterator[f];if(E===void 0)return s.delegate=null,f==="throw"&&l.iterator.return&&(s.method="return",s.arg=void 0,O(l,s),s.method==="throw")||f!=="return"&&(s.method="throw",s.arg=new TypeError("The iterator does not provide a '"+f+"' method")),b;var g=k(E,l.iterator,s.arg);if(g.type==="throw")return s.method="throw",s.arg=g.arg,s.delegate=null,b;var S=g.arg;return S?S.done?(s[l.resultName]=S.value,s.next=l.nextLoc,s.method!=="return"&&(s.method="next",s.arg=void 0),s.delegate=null,b):S:(s.method="throw",s.arg=new TypeError("iterator result is not an object"),s.delegate=null,b)}function C(l){var s={tryLoc:l[0]};1 in l&&(s.catchLoc=l[1]),2 in l&&(s.finallyLoc=l[2],s.afterLoc=l[3]),this.tryEntries.push(s)}function m(l){var s=l.completion||{};s.type="normal",delete s.arg,l.completion=s}function F(l){this.tryEntries=[{tryLoc:"root"}],l.forEach(C,this),this.reset(!0)}function V(l){if(l){var s=l[a];if(s)return s.call(l);if(typeof l.next=="function")return l;if(!isNaN(l.length)){var f=-1,E=function g(){for(;++f<l.length;)if(t.call(l,f))return g.value=l[f],g.done=!1,g;return g.value=void 0,g.done=!0,g};return E.next=E}}return{next:ne}}function ne(){return{value:void 0,done:!0}}return P.prototype=y,r(v,"constructor",{value:y,configurable:!0}),r(y,"constructor",{value:P,configurable:!0}),P.displayName=d(y,c,"GeneratorFunction"),n.isGeneratorFunction=function(l){var s=typeof l=="function"&&l.constructor;return!!s&&(s===P||(s.displayName||s.name)==="GeneratorFunction")},n.mark=function(l){return Object.setPrototypeOf?Object.setPrototypeOf(l,y):(l.__proto__=y,d(l,c,"GeneratorFunction")),l.prototype=Object.create(v),l},n.awrap=function(l){return{__await:l}},h(p.prototype),d(p.prototype,o,function(){return this}),n.AsyncIterator=p,n.async=function(l,s,f,E,g){g===void 0&&(g=Promise);var S=new p(u(l,s,f,E),g);return n.isGeneratorFunction(s)?S:S.next().then(function(M){return M.done?M.value:S.next()})},h(v),d(v,c,"Generator"),d(v,a,function(){return this}),d(v,"toString",function(){return"[object Generator]"}),n.keys=function(l){var s=Object(l),f=[];for(var E in s)f.push(E);return f.reverse(),function g(){for(;f.length;){var S=f.pop();if(S in s)return g.value=S,g.done=!1,g}return g.done=!0,g}},n.values=V,F.prototype={constructor:F,reset:function(l){if(this.prev=0,this.next=0,this.sent=this._sent=void 0,this.done=!1,this.delegate=null,this.method="next",this.arg=void 0,this.tryEntries.forEach(m),!l)for(var s in this)s.charAt(0)==="t"&&t.call(this,s)&&!isNaN(+s.slice(1))&&(this[s]=void 0)},stop:function(){this.done=!0;var l=this.tryEntries[0].completion;if(l.type==="throw")throw l.arg;return this.rval},dispatchException:function(l){if(this.done)throw l;var s=this;function f(L,x){return S.type="throw",S.arg=l,s.next=L,x&&(s.method="next",s.arg=void 0),!!x}for(var E=this.tryEntries.length-1;E>=0;--E){var g=this.tryEntries[E],S=g.completion;if(g.tryLoc==="root")return f("end");if(g.tryLoc<=this.prev){var M=t.call(g,"catchLoc"),I=t.call(g,"finallyLoc");if(M&&I){if(this.prev<g.catchLoc)return f(g.catchLoc,!0);if(this.prev<g.finallyLoc)return f(g.finallyLoc)}else if(M){if(this.prev<g.catchLoc)return f(g.catchLoc,!0)}else{if(!I)throw new Error("try statement without catch or finally");if(this.prev<g.finallyLoc)return f(g.finallyLoc)}}}},abrupt:function(l,s){for(var f=this.tryEntries.length-1;f>=0;--f){var E=this.tryEntries[f];if(E.tryLoc<=this.prev&&t.call(E,"finallyLoc")&&this.prev<E.finallyLoc){var g=E;break}}g&&(l==="break"||l==="continue")&&g.tryLoc<=s&&s<=g.finallyLoc&&(g=null);var S=g?g.completion:{};return S.type=l,S.arg=s,g?(this.method="next",this.next=g.finallyLoc,b):this.complete(S)},complete:function(l,s){if(l.type==="throw")throw l.arg;return l.type==="break"||l.type==="continue"?this.next=l.arg:l.type==="return"?(this.rval=this.arg=l.arg,this.method="return",this.next="end"):l.type==="normal"&&s&&(this.next=s),b},finish:function(l){for(var s=this.tryEntries.length-1;s>=0;--s){var f=this.tryEntries[s];if(f.finallyLoc===l)return this.complete(f.completion,f.afterLoc),m(f),b}},catch:function(l){for(var s=this.tryEntries.length-1;s>=0;--s){var f=this.tryEntries[s];if(f.tryLoc===l){var E=f.completion;if(E.type==="throw"){var g=E.arg;m(f)}return g}}throw new Error("illegal catch attempt")},delegateYield:function(l,s,f){return this.delegate={iterator:V(l),resultName:s,nextLoc:f},this.method==="next"&&(this.arg=void 0),b}},n}function le(n,e,t,r,i,a,o){try{var c=n[a](o),d=c.value}catch(u){t(u);return}c.done?e(d):Promise.resolve(d).then(r,i)}function z(n){return function(){var e=this,t=arguments;return new Promise(function(r,i){var a=n.apply(e,t);function o(d){le(a,r,i,o,c,"next",d)}function c(d){le(a,r,i,o,c,"throw",d)}o(void 0)})}}function pe(n,e){if(!(n instanceof e))throw new TypeError("Cannot call a class as a function")}function fe(n,e){for(var t=0;t<e.length;t++){var r=e[t];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(n,me(r.key),r)}}function ve(n,e,t){return e&&fe(n.prototype,e),t&&fe(n,t),Object.defineProperty(n,"prototype",{writable:!1}),n}function X(n,e,t){return e=me(e),e in n?Object.defineProperty(n,e,{value:t,enumerable:!0,configurable:!0,writable:!0}):n[e]=t,n}function Te(n,e){if(typeof e!="function"&&e!==null)throw new TypeError("Super expression must either be null or a function");n.prototype=Object.create(e&&e.prototype,{constructor:{value:n,writable:!0,configurable:!0}}),Object.defineProperty(n,"prototype",{writable:!1}),e&&J(n,e)}function Y(n){return Y=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(t){return t.__proto__||Object.getPrototypeOf(t)},Y(n)}function J(n,e){return J=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(r,i){return r.__proto__=i,r},J(n,e)}function ge(){if(typeof Reflect>"u"||!Reflect.construct||Reflect.construct.sham)return!1;if(typeof Proxy=="function")return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],function(){})),!0}catch{return!1}}function K(n,e,t){return ge()?K=Reflect.construct.bind():K=function(i,a,o){var c=[null];c.push.apply(c,a);var d=Function.bind.apply(i,c),u=new d;return o&&J(u,o.prototype),u},K.apply(null,arguments)}function Oe(n){return Function.toString.call(n).indexOf("[native code]")!==-1}function ae(n){var e=typeof Map=="function"?new Map:void 0;return ae=function(r){if(r===null||!Oe(r))return r;if(typeof r!="function")throw new TypeError("Super expression must either be null or a function");if(typeof e<"u"){if(e.has(r))return e.get(r);e.set(r,i)}function i(){return K(r,arguments,Y(this).constructor)}return i.prototype=Object.create(r.prototype,{constructor:{value:i,enumerable:!1,writable:!0,configurable:!0}}),J(i,r)},ae(n)}function Z(n){if(n===void 0)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return n}function Se(n,e){if(e&&(typeof e=="object"||typeof e=="function"))return e;if(e!==void 0)throw new TypeError("Derived constructors may only return object or undefined");return Z(n)}function Ce(n){var e=ge();return function(){var r=Y(n),i;if(e){var a=Y(this).constructor;i=Reflect.construct(r,arguments,a)}else i=r.apply(this,arguments);return Se(this,i)}}function Me(n,e){if(typeof n!="object"||n===null)return n;var t=n[Symbol.toPrimitive];if(t!==void 0){var r=t.call(n,e||"default");if(typeof r!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(n)}function me(n){var e=Me(n,"string");return typeof e=="symbol"?e:String(e)}var ye=typeof global<"u"&&{}.toString.call(global)==="[object global]";function de(n,e){return n.indexOf(e.toLowerCase())===0?n:"".concat(e.toLowerCase()).concat(n.substr(0,1).toUpperCase()).concat(n.substr(1))}function je(n){return!!(n&&n.nodeType===1&&"nodeName"in n&&n.ownerDocument&&n.ownerDocument.defaultView)}function Ne(n){return!isNaN(parseFloat(n))&&isFinite(n)&&Math.floor(n)==n}function G(n){return/^(https?:)?\/\/((((player|www)\.)?vimeo\.com)|((player\.)?[a-zA-Z0-9-]+\.(videoji\.(hk|cn)|vimeo\.work)))(?=$|\/)/.test(n)}function we(n){var e=/^https:\/\/player\.((vimeo\.com)|([a-zA-Z0-9-]+\.(videoji\.(hk|cn)|vimeo\.work)))\/video\/\d+/;return e.test(n)}function Re(n){for(var e=(n||"").match(/^(?:https?:)?(?:\/\/)?([^/?]+)/),t=(e&&e[1]||"").replace("player.",""),r=[".videoji.hk",".vimeo.work",".videoji.cn"],i=0,a=r;i<a.length;i++){var o=a[i];if(t.endsWith(o))return t}return"vimeo.com"}function be(){var n=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{},e=n.id,t=n.url,r=e||t;if(!r)throw new Error("An id or url must be passed, either in an options object or as a data-vimeo-id or data-vimeo-url attribute.");if(Ne(r))return"https://vimeo.com/".concat(r);if(G(r))return r.replace("http:","https:");throw e?new TypeError("\u201C".concat(e,"\u201D is not a valid video id.")):new TypeError("\u201C".concat(r,"\u201D is not a vimeo.com url."))}var he=function(e,t,r){var i=arguments.length>3&&arguments[3]!==void 0?arguments[3]:"addEventListener",a=arguments.length>4&&arguments[4]!==void 0?arguments[4]:"removeEventListener",o=typeof t=="string"?[t]:t;return o.forEach(function(c){e[i](c,r)}),{cancel:function(){return o.forEach(function(d){return e[a](d,r)})}}},_e=typeof Array.prototype.indexOf<"u",Ae=typeof window<"u"&&typeof window.postMessage<"u";if(!ye&&(!_e||!Ae))throw new Error("Sorry, the Vimeo Player API is not available in this browser.");var Q=typeof globalThis<"u"?globalThis:typeof window<"u"?window:typeof global<"u"?global:typeof self<"u"?self:{};function Fe(n,e){return e={exports:{}},n(e,e.exports),e.exports}(function(n){if(n.WeakMap)return;var e=Object.prototype.hasOwnProperty,t=Object.defineProperty&&function(){try{return Object.defineProperty({},"x",{value:1}).x===1}catch{}}(),r=function(a,o,c){t?Object.defineProperty(a,o,{configurable:!0,writable:!0,value:c}):a[o]=c};n.WeakMap=function(){function a(){if(this===void 0)throw new TypeError("Constructor WeakMap requires 'new'");if(r(this,"_id",c("_WeakMap")),arguments.length>0)throw new TypeError("WeakMap iterable is not supported")}r(a.prototype,"delete",function(u){if(o(this,"delete"),!i(u))return!1;var k=u[this._id];return k&&k[0]===u?(delete u[this._id],!0):!1}),r(a.prototype,"get",function(u){if(o(this,"get"),!!i(u)){var k=u[this._id];if(k&&k[0]===u)return k[1]}}),r(a.prototype,"has",function(u){if(o(this,"has"),!i(u))return!1;var k=u[this._id];return!!(k&&k[0]===u)}),r(a.prototype,"set",function(u,k){if(o(this,"set"),!i(u))throw new TypeError("Invalid value used as weak map key");var b=u[this._id];return b&&b[0]===u?(b[1]=k,this):(r(u,this._id,[u,k]),this)});function o(u,k){if(!i(u)||!e.call(u,"_id"))throw new TypeError(k+" method called on incompatible receiver "+typeof u)}function c(u){return u+"_"+d()+"."+d()}function d(){return Math.random().toString().substring(2)}return r(a,"_polyfill",!0),a}();function i(a){return Object(a)===a}})(typeof globalThis<"u"?globalThis:typeof self<"u"?self:typeof window<"u"?window:Q);var D=Fe(function(n){(function(t,r,i){r[t]=r[t]||i(),n.exports&&(n.exports=r[t])})("Promise",Q,function(){var t,r,i,a=Object.prototype.toString,o=typeof setImmediate<"u"?function(h){return setImmediate(h)}:setTimeout;try{Object.defineProperty({},"x",{}),t=function(h,p,w,O){return Object.defineProperty(h,p,{value:w,writable:!0,configurable:O!==!1})}}catch{t=function(p,w,O){return p[w]=O,p}}i=function(){var h,p,w;function O(C,m){this.fn=C,this.self=m,this.next=void 0}return{add:function(m,F){w=new O(m,F),p?p.next=w:h=w,p=w,w=void 0},drain:function(){var m=h;for(h=p=r=void 0;m;)m.fn.call(m.self),m=m.next}}}();function c(v,h){i.add(v,h),r||(r=o(i.drain))}function d(v){var h,p=typeof v;return v!=null&&(p=="object"||p=="function")&&(h=v.then),typeof h=="function"?h:!1}function u(){for(var v=0;v<this.chain.length;v++)k(this,this.state===1?this.chain[v].success:this.chain[v].failure,this.chain[v]);this.chain.length=0}function k(v,h,p){var w,O;try{h===!1?p.reject(v.msg):(h===!0?w=v.msg:w=h.call(void 0,v.msg),w===p.promise?p.reject(TypeError("Promise-chain cycle")):(O=d(w))?O.call(w,p.resolve,p.reject):p.resolve(w))}catch(C){p.reject(C)}}function b(v){var h,p=this;if(!p.triggered){p.triggered=!0,p.def&&(p=p.def);try{(h=d(v))?c(function(){var w=new y(p);try{h.call(v,function(){b.apply(w,arguments)},function(){T.apply(w,arguments)})}catch(O){T.call(w,O)}}):(p.msg=v,p.state=1,p.chain.length>0&&c(u,p))}catch(w){T.call(new y(p),w)}}}function T(v){var h=this;h.triggered||(h.triggered=!0,h.def&&(h=h.def),h.msg=v,h.state=2,h.chain.length>0&&c(u,h))}function P(v,h,p,w){for(var O=0;O<h.length;O++)(function(m){v.resolve(h[m]).then(function(V){p(m,V)},w)})(O)}function y(v){this.def=v,this.triggered=!1}function N(v){this.promise=v,this.state=0,this.triggered=!1,this.chain=[],this.msg=void 0}function j(v){if(typeof v!="function")throw TypeError("Not a function");if(this.__NPO__!==0)throw TypeError("Not a promise");this.__NPO__=1;var h=new N(this);this.then=function(w,O){var C={success:typeof w=="function"?w:!0,failure:typeof O=="function"?O:!1};return C.promise=new this.constructor(function(F,V){if(typeof F!="function"||typeof V!="function")throw TypeError("Not a function");C.resolve=F,C.reject=V}),h.chain.push(C),h.state!==0&&c(u,h),C.promise},this.catch=function(w){return this.then(void 0,w)};try{v.call(void 0,function(w){b.call(h,w)},function(w){T.call(h,w)})}catch(p){T.call(h,p)}}var A=t({},"constructor",j,!1);return j.prototype=A,t(A,"__NPO__",0,!1),t(j,"resolve",function(h){var p=this;return h&&typeof h=="object"&&h.__NPO__===1?h:new p(function(O,C){if(typeof O!="function"||typeof C!="function")throw TypeError("Not a function");O(h)})}),t(j,"reject",function(h){return new this(function(w,O){if(typeof w!="function"||typeof O!="function")throw TypeError("Not a function");O(h)})}),t(j,"all",function(h){var p=this;return a.call(h)!="[object Array]"?p.reject(TypeError("Not an array")):h.length===0?p.resolve([]):new p(function(O,C){if(typeof O!="function"||typeof C!="function")throw TypeError("Not a function");var m=h.length,F=Array(m),V=0;P(p,h,function(l,s){F[l]=s,++V===m&&O(F)},C)})}),t(j,"race",function(h){var p=this;return a.call(h)!="[object Array]"?p.reject(TypeError("Not an array")):new p(function(O,C){if(typeof O!="function"||typeof C!="function")throw TypeError("Not a function");P(p,h,function(F,V){O(V)},C)})}),j})}),q=new WeakMap;function H(n,e,t){var r=q.get(n.element)||{};e in r||(r[e]=[]),r[e].push(t),q.set(n.element,r)}function ee(n,e){var t=q.get(n.element)||{};return t[e]||[]}function te(n,e,t){var r=q.get(n.element)||{};if(!r[e])return!0;if(!t)return r[e]=[],q.set(n.element,r),!0;var i=r[e].indexOf(t);return i!==-1&&r[e].splice(i,1),q.set(n.element,r),r[e]&&r[e].length===0}function Ie(n,e){var t=ee(n,e);if(t.length<1)return!1;var r=t.shift();return te(n,e,r),r}function Le(n,e){var t=q.get(n);q.set(e,t),q.delete(n)}function re(n){if(typeof n=="string")try{n=JSON.parse(n)}catch(e){return console.warn(e),{}}return n}function W(n,e,t){if(!(!n.element.contentWindow||!n.element.contentWindow.postMessage)){var r={method:e};t!==void 0&&(r.value=t);var i=parseFloat(navigator.userAgent.toLowerCase().replace(/^.*msie (\d+).*$/,"$1"));i>=8&&i<10&&(r=JSON.stringify(r)),n.element.contentWindow.postMessage(r,n.origin)}}function Ve(n,e){e=re(e);var t=[],r;if(e.event){if(e.event==="error"){var i=ee(n,e.data.method);i.forEach(function(o){var c=new Error(e.data.message);c.name=e.data.name,o.reject(c),te(n,e.data.method,o)})}t=ee(n,"event:".concat(e.event)),r=e.data}else if(e.method){var a=Ie(n,e.method);a&&(t.push(a),r=e.value)}t.forEach(function(o){try{if(typeof o=="function"){o.call(n,r);return}o.resolve(r)}catch{}})}var De=["airplay","audio_tracks","autopause","autoplay","background","byline","cc","chapter_id","chapters","chromecast","color","colors","controls","dnt","end_time","fullscreen","height","id","interactive_params","keyboard","loop","maxheight","maxwidth","muted","play_button_position","playsinline","portrait","progress_bar","quality_selector","responsive","speed","start_time","texttrack","title","transcript","transparent","unmute_button","url","vimeo_logo","volume","watch_full_video","width"];function ke(n){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{};return De.reduce(function(t,r){var i=n.getAttribute("data-vimeo-".concat(r));return(i||i==="")&&(t[r]=i===""?1:i),t},e)}function oe(n,e){var t=n.html;if(!e)throw new TypeError("An element must be provided");if(e.getAttribute("data-vimeo-initialized")!==null)return e.querySelector("iframe");var r=document.createElement("div");return r.innerHTML=t,e.appendChild(r.firstChild),e.setAttribute("data-vimeo-initialized","true"),e.querySelector("iframe")}function Pe(n){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},t=arguments.length>2?arguments[2]:void 0;return new Promise(function(r,i){if(!G(n))throw new TypeError("\u201C".concat(n,"\u201D is not a vimeo.com url."));var a=Re(n),o="https://".concat(a,"/api/oembed.json?url=").concat(encodeURIComponent(n));for(var c in e)e.hasOwnProperty(c)&&(o+="&".concat(c,"=").concat(encodeURIComponent(e[c])));var d="XDomainRequest"in window?new XDomainRequest:new XMLHttpRequest;d.open("GET",o,!0),d.onload=function(){if(d.status===404){i(new Error("\u201C".concat(n,"\u201D was not found.")));return}if(d.status===403){i(new Error("\u201C".concat(n,"\u201D is not embeddable.")));return}try{var u=JSON.parse(d.responseText);if(u.domain_status_code===403){oe(u,t),i(new Error("\u201C".concat(n,"\u201D is not embeddable.")));return}r(u)}catch(k){i(k)}},d.onerror=function(){var u=d.status?" (".concat(d.status,")"):"";i(new Error("There was an error fetching the embed code from Vimeo".concat(u,".")))},d.send()})}function qe(){var n=arguments.length>0&&arguments[0]!==void 0?arguments[0]:document,e=[].slice.call(n.querySelectorAll("[data-vimeo-id], [data-vimeo-url]")),t=function(i){"console"in window&&console.error&&console.error("There was an error creating an embed: ".concat(i))};e.forEach(function(r){try{if(r.getAttribute("data-vimeo-defer")!==null)return;var i=ke(r),a=be(i);Pe(a,i,r).then(function(o){return oe(o,r)}).catch(t)}catch(o){t(o)}})}function $e(){var n=arguments.length>0&&arguments[0]!==void 0?arguments[0]:document;if(!window.VimeoPlayerResizeEmbeds_){window.VimeoPlayerResizeEmbeds_=!0;var e=function(r){if(G(r.origin)&&!(!r.data||r.data.event!=="spacechange")){for(var i=n.querySelectorAll("iframe"),a=0;a<i.length;a++)if(i[a].contentWindow===r.source){var o=i[a].parentElement;o.style.paddingBottom="".concat(r.data.data[0].bottom,"px");break}}};window.addEventListener("message",e)}}function We(){var n=arguments.length>0&&arguments[0]!==void 0?arguments[0]:document;if(!window.VimeoSeoMetadataAppended){window.VimeoSeoMetadataAppended=!0;var e=function(r){if(G(r.origin)){var i=re(r.data);if(!(!i||i.event!=="ready"))for(var a=n.querySelectorAll("iframe"),o=0;o<a.length;o++){var c=a[o],d=c.contentWindow===r.source;if(we(c.src)&&d){var u=new ue(c);u.callMethod("appendVideoMetadata",window.location.href)}}}};window.addEventListener("message",e)}}function ze(){var n=arguments.length>0&&arguments[0]!==void 0?arguments[0]:document;if(!window.VimeoCheckedUrlTimeParam){window.VimeoCheckedUrlTimeParam=!0;var e=function(i){"console"in window&&console.error&&console.error("There was an error getting video Id: ".concat(i))},t=function(i){if(G(i.origin)){var a=re(i.data);if(!(!a||a.event!=="ready"))for(var o=n.querySelectorAll("iframe"),c=function(){var k=o[d],b=k.contentWindow===i.source;if(we(k.src)&&b){var T=new ue(k);T.getVideoId().then(function(P){var y=new RegExp("[?&]vimeo_t_".concat(P,"=([^&#]*)")).exec(window.location.href);if(y&&y[1]){var N=decodeURI(y[1]);T.setCurrentTime(N)}}).catch(e)}},d=0;d<o.length;d++)c()}};window.addEventListener("message",t)}}function Ge(){var n=function(){for(var r,i=[["requestFullscreen","exitFullscreen","fullscreenElement","fullscreenEnabled","fullscreenchange","fullscreenerror"],["webkitRequestFullscreen","webkitExitFullscreen","webkitFullscreenElement","webkitFullscreenEnabled","webkitfullscreenchange","webkitfullscreenerror"],["webkitRequestFullScreen","webkitCancelFullScreen","webkitCurrentFullScreenElement","webkitCancelFullScreen","webkitfullscreenchange","webkitfullscreenerror"],["mozRequestFullScreen","mozCancelFullScreen","mozFullScreenElement","mozFullScreenEnabled","mozfullscreenchange","mozfullscreenerror"],["msRequestFullscreen","msExitFullscreen","msFullscreenElement","msFullscreenEnabled","MSFullscreenChange","MSFullscreenError"]],a=0,o=i.length,c={};a<o;a++)if(r=i[a],r&&r[1]in document){for(a=0;a<r.length;a++)c[i[0][a]]=r[a];return c}return!1}(),e={fullscreenchange:n.fullscreenchange,fullscreenerror:n.fullscreenerror},t={request:function(i){return new Promise(function(a,o){var c=function u(){t.off("fullscreenchange",u),a()};t.on("fullscreenchange",c),i=i||document.documentElement;var d=i[n.requestFullscreen]();d instanceof Promise&&d.then(c).catch(o)})},exit:function(){return new Promise(function(i,a){if(!t.isFullscreen){i();return}var o=function d(){t.off("fullscreenchange",d),i()};t.on("fullscreenchange",o);var c=document[n.exitFullscreen]();c instanceof Promise&&c.then(o).catch(a)})},on:function(i,a){var o=e[i];o&&document.addEventListener(o,a)},off:function(i,a){var o=e[i];o&&document.removeEventListener(o,a)}};return Object.defineProperties(t,{isFullscreen:{get:function(){return!!document[n.fullscreenElement]}},element:{enumerable:!0,get:function(){return document[n.fullscreenElement]}},isEnabled:{enumerable:!0,get:function(){return!!document[n.fullscreenEnabled]}}}),t}var xe={role:"viewer",autoPlayMuted:!0,allowedDrift:.3,maxAllowedDrift:1,minCheckInterval:.1,maxRateAdjustment:.2,maxTimeToCatchUp:1},Ue=function(n){Te(t,n);var e=Ce(t);function t(r,i){var a,o=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{},c=arguments.length>3?arguments[3]:void 0;return pe(this,t),a=e.call(this),X(Z(a),"logger",void 0),X(Z(a),"speedAdjustment",0),X(Z(a),"adjustSpeed",function(){var d=z(R().mark(function u(k,b){var T;return R().wrap(function(y){for(;;)switch(y.prev=y.next){case 0:if(a.speedAdjustment!==b){y.next=2;break}return y.abrupt("return");case 2:return y.next=4,k.getPlaybackRate();case 4:return y.t0=y.sent,y.t1=a.speedAdjustment,y.t2=y.t0-y.t1,y.t3=b,T=y.t2+y.t3,a.log("New playbackRate:  ".concat(T)),y.next=12,k.setPlaybackRate(T);case 12:a.speedAdjustment=b;case 13:case"end":return y.stop()}},u)}));return function(u,k){return d.apply(this,arguments)}}()),a.logger=c,a.init(i,r,ce(ce({},xe),o)),a}return ve(t,[{key:"disconnect",value:function(){this.dispatchEvent(new Event("disconnect"))}},{key:"init",value:function(){var r=z(R().mark(function a(o,c,d){var u=this,k,b,T;return R().wrap(function(y){for(;;)switch(y.prev=y.next){case 0:return y.next=2,this.waitForTOReadyState(o,"open");case 2:if(d.role!=="viewer"){y.next=10;break}return y.next=5,this.updatePlayer(o,c,d);case 5:k=he(o,"change",function(){return u.updatePlayer(o,c,d)}),b=this.maintainPlaybackPosition(o,c,d),this.addEventListener("disconnect",function(){b.cancel(),k.cancel()}),y.next=14;break;case 10:return y.next=12,this.updateTimingObject(o,c);case 12:T=he(c,["seeked","play","pause","ratechange"],function(){return u.updateTimingObject(o,c)},"on","off"),this.addEventListener("disconnect",function(){return T.cancel()});case 14:case"end":return y.stop()}},a,this)}));function i(a,o,c){return r.apply(this,arguments)}return i}()},{key:"updateTimingObject",value:function(){var r=z(R().mark(function a(o,c){return R().wrap(function(u){for(;;)switch(u.prev=u.next){case 0:return u.t0=o,u.next=3,c.getCurrentTime();case 3:return u.t1=u.sent,u.next=6,c.getPaused();case 6:if(!u.sent){u.next=10;break}u.t2=0,u.next=13;break;case 10:return u.next=12,c.getPlaybackRate();case 12:u.t2=u.sent;case 13:u.t3=u.t2,u.t4={position:u.t1,velocity:u.t3},u.t0.update.call(u.t0,u.t4);case 16:case"end":return u.stop()}},a)}));function i(a,o){return r.apply(this,arguments)}return i}()},{key:"updatePlayer",value:function(){var r=z(R().mark(function a(o,c,d){var u,k,b;return R().wrap(function(P){for(;;)switch(P.prev=P.next){case 0:if(u=o.query(),k=u.position,b=u.velocity,typeof k=="number"&&c.setCurrentTime(k),typeof b!="number"){P.next=25;break}if(b!==0){P.next=11;break}return P.next=6,c.getPaused();case 6:if(P.t0=P.sent,P.t0!==!1){P.next=9;break}c.pause();case 9:P.next=25;break;case 11:if(!(b>0)){P.next=25;break}return P.next=14,c.getPaused();case 14:if(P.t1=P.sent,P.t1!==!0){P.next=19;break}return P.next=18,c.play().catch(function(){var y=z(R().mark(function N(j){return R().wrap(function(v){for(;;)switch(v.prev=v.next){case 0:if(!(j.name==="NotAllowedError"&&d.autoPlayMuted)){v.next=5;break}return v.next=3,c.setMuted(!0);case 3:return v.next=5,c.play().catch(function(h){return console.error("Couldn't play the video from TimingSrcConnector. Error:",h)});case 5:case"end":return v.stop()}},N)}));return function(N){return y.apply(this,arguments)}}());case 18:this.updatePlayer(o,c,d);case 19:return P.next=21,c.getPlaybackRate();case 21:if(P.t2=P.sent,P.t3=b,P.t2===P.t3){P.next=25;break}c.setPlaybackRate(b);case 25:case"end":return P.stop()}},a,this)}));function i(a,o,c){return r.apply(this,arguments)}return i}()},{key:"maintainPlaybackPosition",value:function(i,a,o){var c=this,d=o.allowedDrift,u=o.maxAllowedDrift,k=o.minCheckInterval,b=o.maxRateAdjustment,T=o.maxTimeToCatchUp,P=Math.min(T,Math.max(k,u))*1e3,y=function(){var j=z(R().mark(function A(){var v,h,p,w,O;return R().wrap(function(m){for(;;)switch(m.prev=m.next){case 0:if(m.t0=i.query().velocity===0,m.t0){m.next=6;break}return m.next=4,a.getPaused();case 4:m.t1=m.sent,m.t0=m.t1===!0;case 6:if(!m.t0){m.next=8;break}return m.abrupt("return");case 8:return m.t2=i.query().position,m.next=11,a.getCurrentTime();case 11:if(m.t3=m.sent,v=m.t2-m.t3,h=Math.abs(v),c.log("Drift: ".concat(v)),!(h>u)){m.next=22;break}return m.next=18,c.adjustSpeed(a,0);case 18:a.setCurrentTime(i.query().position),c.log("Resync by currentTime"),m.next=29;break;case 22:if(!(h>d)){m.next=29;break}return p=h/T,w=b,O=p<w?(w-p)/2:w,m.next=28,c.adjustSpeed(a,O*Math.sign(v));case 28:c.log("Resync by playbackRate");case 29:case"end":return m.stop()}},A)}));return function(){return j.apply(this,arguments)}}(),N=setInterval(function(){return y()},P);return{cancel:function(){return clearInterval(N)}}}},{key:"log",value:function(i){var a;(a=this.logger)===null||a===void 0||a.call(this,"TimingSrcConnector: ".concat(i))}},{key:"waitForTOReadyState",value:function(i,a){return new Promise(function(o){var c=function d(){i.readyState===a?o():i.addEventListener("readystatechange",d,{once:!0})};c()})}}]),t}(ae(EventTarget)),U=new WeakMap,ie=new WeakMap,_={},ue=function(){function n(e){var t=this,r=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{};if(pe(this,n),window.jQuery&&e instanceof jQuery&&(e.length>1&&window.console&&console.warn&&console.warn("A jQuery object with multiple elements was passed, using the first element."),e=e[0]),typeof document<"u"&&typeof e=="string"&&(e=document.getElementById(e)),!je(e))throw new TypeError("You must pass either a valid element or a valid id.");if(e.nodeName!=="IFRAME"){var i=e.querySelector("iframe");i&&(e=i)}if(e.nodeName==="IFRAME"&&!G(e.getAttribute("src")||""))throw new Error("The player element passed isn\u2019t a Vimeo embed.");if(U.has(e))return U.get(e);this._window=e.ownerDocument.defaultView,this.element=e,this.origin="*";var a=new D(function(c,d){if(t._onMessage=function(b){if(!(!G(b.origin)||t.element.contentWindow!==b.source)){t.origin==="*"&&(t.origin=b.origin);var T=re(b.data),P=T&&T.event==="error",y=P&&T.data&&T.data.method==="ready";if(y){var N=new Error(T.data.message);N.name=T.data.name,d(N);return}var j=T&&T.event==="ready",A=T&&T.method==="ping";if(j||A){t.element.setAttribute("data-ready","true"),c();return}Ve(t,T)}},t._window.addEventListener("message",t._onMessage),t.element.nodeName!=="IFRAME"){var u=ke(e,r),k=be(u);Pe(k,u,e).then(function(b){var T=oe(b,e);return t.element=T,t._originalElement=e,Le(e,T),U.set(t.element,t),b}).catch(d)}});if(ie.set(this,a),U.set(this.element,this),this.element.nodeName==="IFRAME"&&W(this,"ping"),_.isEnabled){var o=function(){return _.exit()};this.fullscreenchangeHandler=function(){_.isFullscreen?H(t,"event:exitFullscreen",o):te(t,"event:exitFullscreen",o),t.ready().then(function(){W(t,"fullscreenchange",_.isFullscreen)})},_.on("fullscreenchange",this.fullscreenchangeHandler)}return this}return ve(n,[{key:"callMethod",value:function(t){var r=this,i=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{};return new D(function(a,o){return r.ready().then(function(){H(r,t,{resolve:a,reject:o}),W(r,t,i)}).catch(o)})}},{key:"get",value:function(t){var r=this;return new D(function(i,a){return t=de(t,"get"),r.ready().then(function(){H(r,t,{resolve:i,reject:a}),W(r,t)}).catch(a)})}},{key:"set",value:function(t,r){var i=this;return new D(function(a,o){if(t=de(t,"set"),r==null)throw new TypeError("There must be a value to set.");return i.ready().then(function(){H(i,t,{resolve:a,reject:o}),W(i,t,r)}).catch(o)})}},{key:"on",value:function(t,r){if(!t)throw new TypeError("You must pass an event name.");if(!r)throw new TypeError("You must pass a callback function.");if(typeof r!="function")throw new TypeError("The callback must be a function.");var i=ee(this,"event:".concat(t));i.length===0&&this.callMethod("addEventListener",t).catch(function(){}),H(this,"event:".concat(t),r)}},{key:"off",value:function(t,r){if(!t)throw new TypeError("You must pass an event name.");if(r&&typeof r!="function")throw new TypeError("The callback must be a function.");var i=te(this,"event:".concat(t),r);i&&this.callMethod("removeEventListener",t).catch(function(a){})}},{key:"loadVideo",value:function(t){return this.callMethod("loadVideo",t)}},{key:"ready",value:function(){var t=ie.get(this)||new D(function(r,i){i(new Error("Unknown player. Probably unloaded."))});return D.resolve(t)}},{key:"addCuePoint",value:function(t){var r=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{};return this.callMethod("addCuePoint",{time:t,data:r})}},{key:"removeCuePoint",value:function(t){return this.callMethod("removeCuePoint",t)}},{key:"enableTextTrack",value:function(t,r){if(!t)throw new TypeError("You must pass a language.");return this.callMethod("enableTextTrack",{language:t,kind:r})}},{key:"disableTextTrack",value:function(){return this.callMethod("disableTextTrack")}},{key:"pause",value:function(){return this.callMethod("pause")}},{key:"play",value:function(){return this.callMethod("play")}},{key:"requestFullscreen",value:function(){return _.isEnabled?_.request(this.element):this.callMethod("requestFullscreen")}},{key:"exitFullscreen",value:function(){return _.isEnabled?_.exit():this.callMethod("exitFullscreen")}},{key:"getFullscreen",value:function(){return _.isEnabled?D.resolve(_.isFullscreen):this.get("fullscreen")}},{key:"requestPictureInPicture",value:function(){return this.callMethod("requestPictureInPicture")}},{key:"exitPictureInPicture",value:function(){return this.callMethod("exitPictureInPicture")}},{key:"getPictureInPicture",value:function(){return this.get("pictureInPicture")}},{key:"remotePlaybackPrompt",value:function(){return this.callMethod("remotePlaybackPrompt")}},{key:"unload",value:function(){return this.callMethod("unload")}},{key:"destroy",value:function(){var t=this;return new D(function(r){if(ie.delete(t),U.delete(t.element),t._originalElement&&(U.delete(t._originalElement),t._originalElement.removeAttribute("data-vimeo-initialized")),t.element&&t.element.nodeName==="IFRAME"&&t.element.parentNode&&(t.element.parentNode.parentNode&&t._originalElement&&t._originalElement!==t.element.parentNode?t.element.parentNode.parentNode.removeChild(t.element.parentNode):t.element.parentNode.removeChild(t.element)),t.element&&t.element.nodeName==="DIV"&&t.element.parentNode){t.element.removeAttribute("data-vimeo-initialized");var i=t.element.querySelector("iframe");i&&i.parentNode&&(i.parentNode.parentNode&&t._originalElement&&t._originalElement!==i.parentNode?i.parentNode.parentNode.removeChild(i.parentNode):i.parentNode.removeChild(i))}t._window.removeEventListener("message",t._onMessage),_.isEnabled&&_.off("fullscreenchange",t.fullscreenchangeHandler),r()})}},{key:"getAutopause",value:function(){return this.get("autopause")}},{key:"setAutopause",value:function(t){return this.set("autopause",t)}},{key:"getBuffered",value:function(){return this.get("buffered")}},{key:"getCameraProps",value:function(){return this.get("cameraProps")}},{key:"setCameraProps",value:function(t){return this.set("cameraProps",t)}},{key:"getChapters",value:function(){return this.get("chapters")}},{key:"getCurrentChapter",value:function(){return this.get("currentChapter")}},{key:"getColor",value:function(){return this.get("color")}},{key:"getColors",value:function(){return D.all([this.get("colorOne"),this.get("colorTwo"),this.get("colorThree"),this.get("colorFour")])}},{key:"setColor",value:function(t){return this.set("color",t)}},{key:"setColors",value:function(t){if(!Array.isArray(t))return new D(function(a,o){return o(new TypeError("Argument must be an array."))});var r=new D(function(a){return a(null)}),i=[t[0]?this.set("colorOne",t[0]):r,t[1]?this.set("colorTwo",t[1]):r,t[2]?this.set("colorThree",t[2]):r,t[3]?this.set("colorFour",t[3]):r];return D.all(i)}},{key:"getCuePoints",value:function(){return this.get("cuePoints")}},{key:"getCurrentTime",value:function(){return this.get("currentTime")}},{key:"setCurrentTime",value:function(t){return this.set("currentTime",t)}},{key:"getDuration",value:function(){return this.get("duration")}},{key:"getEnded",value:function(){return this.get("ended")}},{key:"getLoop",value:function(){return this.get("loop")}},{key:"setLoop",value:function(t){return this.set("loop",t)}},{key:"setMuted",value:function(t){return this.set("muted",t)}},{key:"getMuted",value:function(){return this.get("muted")}},{key:"getPaused",value:function(){return this.get("paused")}},{key:"getPlaybackRate",value:function(){return this.get("playbackRate")}},{key:"setPlaybackRate",value:function(t){return this.set("playbackRate",t)}},{key:"getPlayed",value:function(){return this.get("played")}},{key:"getQualities",value:function(){return this.get("qualities")}},{key:"getQuality",value:function(){return this.get("quality")}},{key:"setQuality",value:function(t){return this.set("quality",t)}},{key:"getRemotePlaybackAvailability",value:function(){return this.get("remotePlaybackAvailability")}},{key:"getRemotePlaybackState",value:function(){return this.get("remotePlaybackState")}},{key:"getSeekable",value:function(){return this.get("seekable")}},{key:"getSeeking",value:function(){return this.get("seeking")}},{key:"getTextTracks",value:function(){return this.get("textTracks")}},{key:"getVideoEmbedCode",value:function(){return this.get("videoEmbedCode")}},{key:"getVideoId",value:function(){return this.get("videoId")}},{key:"getVideoTitle",value:function(){return this.get("videoTitle")}},{key:"getVideoWidth",value:function(){return this.get("videoWidth")}},{key:"getVideoHeight",value:function(){return this.get("videoHeight")}},{key:"getVideoUrl",value:function(){return this.get("videoUrl")}},{key:"getVolume",value:function(){return this.get("volume")}},{key:"setVolume",value:function(t){return this.set("volume",t)}},{key:"setTimingSrc",value:function(){var e=z(R().mark(function r(i,a){var o=this,c;return R().wrap(function(u){for(;;)switch(u.prev=u.next){case 0:if(i){u.next=2;break}throw new TypeError("A Timing Object must be provided.");case 2:return u.next=4,this.ready();case 4:return c=new Ue(this,i,a),W(this,"notifyTimingObjectConnect"),c.addEventListener("disconnect",function(){return W(o,"notifyTimingObjectDisconnect")}),u.abrupt("return",c);case 8:case"end":return u.stop()}},r,this)}));function t(r,i){return e.apply(this,arguments)}return t}()}]),n}();ye||(_=Ge(),qe(),$e(),We(),ze());var Ee=ue;window.Vimeo=Ee;})();
/*! Bundled license information:

@vimeo/player/dist/player.es.js:
  (*! @vimeo/player v2.24.0 | (c) 2024 Vimeo | MIT License | https://github.com/vimeo/player.js *)
  (*!
   * weakmap-polyfill v2.0.4 - ECMAScript6 WeakMap polyfill
   * https://github.com/polygonplanet/weakmap-polyfill
   * Copyright (c) 2015-2021 polygonplanet <polygon.planet.aqua@gmail.com>
   * @license MIT
   *)
  (*! Native Promise Only
      v0.8.1 (c) Kyle Simpson
      MIT License: http://getify.mit-license.org
  *)
*/