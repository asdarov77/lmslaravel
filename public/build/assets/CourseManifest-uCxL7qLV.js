import{E as Za,n as Z,v as er,G as tr,_ as ar,a as rr,m as nr,c as D,b as Ee,i as oe,e as B,d as z,g as k,t as E,w as P,f as N,F as he,D as G,o as O,z as Oe,j as Te,A as ir,H as vt}from"./app-tfkZ8itt.js";import{p as or}from"./Popup-BDhe9nZi.js";/*!
 * Font Awesome Free 7.1.0 by @fontawesome - https://fontawesome.com
 * License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License)
 * Copyright 2025 Fonticons, Inc.
 */function We(e,t){(t==null||t>e.length)&&(t=e.length);for(var a=0,r=Array(t);a<t;a++)r[a]=e[a];return r}function sr(e){if(Array.isArray(e))return e}function lr(e){if(Array.isArray(e))return We(e)}function fr(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function ur(e,t){for(var a=0;a<t.length;a++){var r=t[a];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,Kt(r.key),r)}}function cr(e,t,a){return t&&ur(e.prototype,t),Object.defineProperty(e,"prototype",{writable:!1}),e}function pe(e,t){var a=typeof Symbol<"u"&&e[Symbol.iterator]||e["@@iterator"];if(!a){if(Array.isArray(e)||(a=rt(e))||t){a&&(e=a);var r=0,n=function(){};return{s:n,n:function(){return r>=e.length?{done:!0}:{done:!1,value:e[r++]}},e:function(l){throw l},f:n}}throw new TypeError(`Invalid attempt to iterate non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}var i,o=!0,s=!1;return{s:function(){a=a.call(e)},n:function(){var l=a.next();return o=l.done,l},e:function(l){s=!0,i=l},f:function(){try{o||a.return==null||a.return()}finally{if(s)throw i}}}}function p(e,t,a){return(t=Kt(t))in e?Object.defineProperty(e,t,{value:a,enumerable:!0,configurable:!0,writable:!0}):e[t]=a,e}function dr(e){if(typeof Symbol<"u"&&e[Symbol.iterator]!=null||e["@@iterator"]!=null)return Array.from(e)}function mr(e,t){var a=e==null?null:typeof Symbol<"u"&&e[Symbol.iterator]||e["@@iterator"];if(a!=null){var r,n,i,o,s=[],l=!0,u=!1;try{if(i=(a=a.call(e)).next,t===0){if(Object(a)!==a)return;l=!1}else for(;!(l=(r=i.call(a)).done)&&(s.push(r.value),s.length!==t);l=!0);}catch(d){u=!0,n=d}finally{try{if(!l&&a.return!=null&&(o=a.return(),Object(o)!==o))return}finally{if(u)throw n}}return s}}function hr(){throw new TypeError(`Invalid attempt to destructure non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function vr(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function gt(e,t){var a=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(n){return Object.getOwnPropertyDescriptor(e,n).enumerable})),a.push.apply(a,r)}return a}function f(e){for(var t=1;t<arguments.length;t++){var a=arguments[t]!=null?arguments[t]:{};t%2?gt(Object(a),!0).forEach(function(r){p(e,r,a[r])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(a)):gt(Object(a)).forEach(function(r){Object.defineProperty(e,r,Object.getOwnPropertyDescriptor(a,r))})}return e}function Ae(e,t){return sr(e)||mr(e,t)||rt(e,t)||hr()}function L(e){return lr(e)||dr(e)||rt(e)||vr()}function gr(e,t){if(typeof e!="object"||!e)return e;var a=e[Symbol.toPrimitive];if(a!==void 0){var r=a.call(e,t);if(typeof r!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}function Kt(e){var t=gr(e,"string");return typeof t=="symbol"?t:t+""}function xe(e){"@babel/helpers - typeof";return xe=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},xe(e)}function rt(e,t){if(e){if(typeof e=="string")return We(e,t);var a={}.toString.call(e).slice(8,-1);return a==="Object"&&e.constructor&&(a=e.constructor.name),a==="Map"||a==="Set"?Array.from(e):a==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(a)?We(e,t):void 0}}var pt=function(){},nt={},Xt={},Jt=null,qt={mark:pt,measure:pt};try{typeof window<"u"&&(nt=window),typeof document<"u"&&(Xt=document),typeof MutationObserver<"u"&&(Jt=MutationObserver),typeof performance<"u"&&(qt=performance)}catch{}var pr=nt.navigator||{},yt=pr.userAgent,bt=yt===void 0?"":yt,H=nt,w=Xt,xt=Jt,ve=qt;H.document;var U=!!w.documentElement&&!!w.head&&typeof w.addEventListener=="function"&&typeof w.createElement=="function",Qt=~bt.indexOf("MSIE")||~bt.indexOf("Trident/"),Ne,yr=/fa(k|kd|s|r|l|t|d|dr|dl|dt|b|slr|slpr|wsb|tl|ns|nds|es|jr|jfr|jdr|usb|ufsb|udsb|cr|ss|sr|sl|st|sds|sdr|sdl|sdt)?[\-\ ]/,br=/Font ?Awesome ?([567 ]*)(Solid|Regular|Light|Thin|Duotone|Brands|Free|Pro|Sharp Duotone|Sharp|Kit|Notdog Duo|Notdog|Chisel|Etch|Thumbprint|Jelly Fill|Jelly Duo|Jelly|Utility|Utility Fill|Utility Duo|Slab Press|Slab|Whiteboard)?.*/i,Zt={classic:{fa:"solid",fas:"solid","fa-solid":"solid",far:"regular","fa-regular":"regular",fal:"light","fa-light":"light",fat:"thin","fa-thin":"thin",fab:"brands","fa-brands":"brands"},duotone:{fa:"solid",fad:"solid","fa-solid":"solid","fa-duotone":"solid",fadr:"regular","fa-regular":"regular",fadl:"light","fa-light":"light",fadt:"thin","fa-thin":"thin"},sharp:{fa:"solid",fass:"solid","fa-solid":"solid",fasr:"regular","fa-regular":"regular",fasl:"light","fa-light":"light",fast:"thin","fa-thin":"thin"},"sharp-duotone":{fa:"solid",fasds:"solid","fa-solid":"solid",fasdr:"regular","fa-regular":"regular",fasdl:"light","fa-light":"light",fasdt:"thin","fa-thin":"thin"},slab:{"fa-regular":"regular",faslr:"regular"},"slab-press":{"fa-regular":"regular",faslpr:"regular"},thumbprint:{"fa-light":"light",fatl:"light"},whiteboard:{"fa-semibold":"semibold",fawsb:"semibold"},notdog:{"fa-solid":"solid",fans:"solid"},"notdog-duo":{"fa-solid":"solid",fands:"solid"},etch:{"fa-solid":"solid",faes:"solid"},jelly:{"fa-regular":"regular",fajr:"regular"},"jelly-fill":{"fa-regular":"regular",fajfr:"regular"},"jelly-duo":{"fa-regular":"regular",fajdr:"regular"},chisel:{"fa-regular":"regular",facr:"regular"},utility:{"fa-semibold":"semibold",fausb:"semibold"},"utility-duo":{"fa-semibold":"semibold",faudsb:"semibold"},"utility-fill":{"fa-semibold":"semibold",faufsb:"semibold"}},xr={GROUP:"duotone-group",PRIMARY:"primary",SECONDARY:"secondary"},ea=["fa-classic","fa-duotone","fa-sharp","fa-sharp-duotone","fa-thumbprint","fa-whiteboard","fa-notdog","fa-notdog-duo","fa-chisel","fa-etch","fa-jelly","fa-jelly-fill","fa-jelly-duo","fa-slab","fa-slab-press","fa-utility","fa-utility-duo","fa-utility-fill"],C="classic",ce="duotone",ta="sharp",aa="sharp-duotone",ra="chisel",na="etch",ia="jelly",oa="jelly-duo",sa="jelly-fill",la="notdog",fa="notdog-duo",ua="slab",ca="slab-press",da="thumbprint",ma="utility",ha="utility-duo",va="utility-fill",ga="whiteboard",wr="Classic",kr="Duotone",Sr="Sharp",Ar="Sharp Duotone",Ir="Chisel",Pr="Etch",Cr="Jelly",_r="Jelly Duo",Fr="Jelly Fill",Er="Notdog",Or="Notdog Duo",Tr="Slab",Nr="Slab Press",jr="Thumbprint",Lr="Utility",Dr="Utility Duo",Mr="Utility Fill",zr="Whiteboard",pa=[C,ce,ta,aa,ra,na,ia,oa,sa,la,fa,ua,ca,da,ma,ha,va,ga];Ne={},p(p(p(p(p(p(p(p(p(p(Ne,C,wr),ce,kr),ta,Sr),aa,Ar),ra,Ir),na,Pr),ia,Cr),oa,_r),sa,Fr),la,Er),p(p(p(p(p(p(p(p(Ne,fa,Or),ua,Tr),ca,Nr),da,jr),ma,Lr),ha,Dr),va,Mr),ga,zr);var Rr={classic:{900:"fas",400:"far",normal:"far",300:"fal",100:"fat"},duotone:{900:"fad",400:"fadr",300:"fadl",100:"fadt"},sharp:{900:"fass",400:"fasr",300:"fasl",100:"fast"},"sharp-duotone":{900:"fasds",400:"fasdr",300:"fasdl",100:"fasdt"},slab:{400:"faslr"},"slab-press":{400:"faslpr"},whiteboard:{600:"fawsb"},thumbprint:{300:"fatl"},notdog:{900:"fans"},"notdog-duo":{900:"fands"},etch:{900:"faes"},chisel:{400:"facr"},jelly:{400:"fajr"},"jelly-fill":{400:"fajfr"},"jelly-duo":{400:"fajdr"},utility:{600:"fausb"},"utility-duo":{600:"faudsb"},"utility-fill":{600:"faufsb"}},$r={"Font Awesome 7 Free":{900:"fas",400:"far"},"Font Awesome 7 Pro":{900:"fas",400:"far",normal:"far",300:"fal",100:"fat"},"Font Awesome 7 Brands":{400:"fab",normal:"fab"},"Font Awesome 7 Duotone":{900:"fad",400:"fadr",normal:"fadr",300:"fadl",100:"fadt"},"Font Awesome 7 Sharp":{900:"fass",400:"fasr",normal:"fasr",300:"fasl",100:"fast"},"Font Awesome 7 Sharp Duotone":{900:"fasds",400:"fasdr",normal:"fasdr",300:"fasdl",100:"fasdt"},"Font Awesome 7 Jelly":{400:"fajr",normal:"fajr"},"Font Awesome 7 Jelly Fill":{400:"fajfr",normal:"fajfr"},"Font Awesome 7 Jelly Duo":{400:"fajdr",normal:"fajdr"},"Font Awesome 7 Slab":{400:"faslr",normal:"faslr"},"Font Awesome 7 Slab Press":{400:"faslpr",normal:"faslpr"},"Font Awesome 7 Thumbprint":{300:"fatl",normal:"fatl"},"Font Awesome 7 Notdog":{900:"fans",normal:"fans"},"Font Awesome 7 Notdog Duo":{900:"fands",normal:"fands"},"Font Awesome 7 Etch":{900:"faes",normal:"faes"},"Font Awesome 7 Chisel":{400:"facr",normal:"facr"},"Font Awesome 7 Whiteboard":{600:"fawsb",normal:"fawsb"},"Font Awesome 7 Utility":{600:"fausb",normal:"fausb"},"Font Awesome 7 Utility Duo":{600:"faudsb",normal:"faudsb"},"Font Awesome 7 Utility Fill":{600:"faufsb",normal:"faufsb"}},Wr=new Map([["classic",{defaultShortPrefixId:"fas",defaultStyleId:"solid",styleIds:["solid","regular","light","thin","brands"],futureStyleIds:[],defaultFontWeight:900}],["duotone",{defaultShortPrefixId:"fad",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["sharp",{defaultShortPrefixId:"fass",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["sharp-duotone",{defaultShortPrefixId:"fasds",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["chisel",{defaultShortPrefixId:"facr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["etch",{defaultShortPrefixId:"faes",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["jelly",{defaultShortPrefixId:"fajr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["jelly-duo",{defaultShortPrefixId:"fajdr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["jelly-fill",{defaultShortPrefixId:"fajfr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["notdog",{defaultShortPrefixId:"fans",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["notdog-duo",{defaultShortPrefixId:"fands",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["slab",{defaultShortPrefixId:"faslr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab-press",{defaultShortPrefixId:"faslpr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["thumbprint",{defaultShortPrefixId:"fatl",defaultStyleId:"light",styleIds:["light"],futureStyleIds:[],defaultFontWeight:300}],["utility",{defaultShortPrefixId:"fausb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["utility-duo",{defaultShortPrefixId:"faudsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["utility-fill",{defaultShortPrefixId:"faufsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["whiteboard",{defaultShortPrefixId:"fawsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}]]),Ur={chisel:{regular:"facr"},classic:{brands:"fab",light:"fal",regular:"far",solid:"fas",thin:"fat"},duotone:{light:"fadl",regular:"fadr",solid:"fad",thin:"fadt"},etch:{solid:"faes"},jelly:{regular:"fajr"},"jelly-duo":{regular:"fajdr"},"jelly-fill":{regular:"fajfr"},notdog:{solid:"fans"},"notdog-duo":{solid:"fands"},sharp:{light:"fasl",regular:"fasr",solid:"fass",thin:"fast"},"sharp-duotone":{light:"fasdl",regular:"fasdr",solid:"fasds",thin:"fasdt"},slab:{regular:"faslr"},"slab-press":{regular:"faslpr"},thumbprint:{light:"fatl"},utility:{semibold:"fausb"},"utility-duo":{semibold:"faudsb"},"utility-fill":{semibold:"faufsb"},whiteboard:{semibold:"fawsb"}},ya=["fak","fa-kit","fakd","fa-kit-duotone"],wt={kit:{fak:"kit","fa-kit":"kit"},"kit-duotone":{fakd:"kit-duotone","fa-kit-duotone":"kit-duotone"}},Br=["kit"],Hr="kit",Yr="kit-duotone",Vr="Kit",Gr="Kit Duotone";p(p({},Hr,Vr),Yr,Gr);var Kr={kit:{"fa-kit":"fak"}},Xr={"Font Awesome Kit":{400:"fak",normal:"fak"},"Font Awesome Kit Duotone":{400:"fakd",normal:"fakd"}},Jr={kit:{fak:"fa-kit"}},kt={kit:{kit:"fak"},"kit-duotone":{"kit-duotone":"fakd"}},je,ge={GROUP:"duotone-group",SWAP_OPACITY:"swap-opacity",PRIMARY:"primary",SECONDARY:"secondary"},qr=["fa-classic","fa-duotone","fa-sharp","fa-sharp-duotone","fa-thumbprint","fa-whiteboard","fa-notdog","fa-notdog-duo","fa-chisel","fa-etch","fa-jelly","fa-jelly-fill","fa-jelly-duo","fa-slab","fa-slab-press","fa-utility","fa-utility-duo","fa-utility-fill"],Qr="classic",Zr="duotone",en="sharp",tn="sharp-duotone",an="chisel",rn="etch",nn="jelly",on="jelly-duo",sn="jelly-fill",ln="notdog",fn="notdog-duo",un="slab",cn="slab-press",dn="thumbprint",mn="utility",hn="utility-duo",vn="utility-fill",gn="whiteboard",pn="Classic",yn="Duotone",bn="Sharp",xn="Sharp Duotone",wn="Chisel",kn="Etch",Sn="Jelly",An="Jelly Duo",In="Jelly Fill",Pn="Notdog",Cn="Notdog Duo",_n="Slab",Fn="Slab Press",En="Thumbprint",On="Utility",Tn="Utility Duo",Nn="Utility Fill",jn="Whiteboard";je={},p(p(p(p(p(p(p(p(p(p(je,Qr,pn),Zr,yn),en,bn),tn,xn),an,wn),rn,kn),nn,Sn),on,An),sn,In),ln,Pn),p(p(p(p(p(p(p(p(je,fn,Cn),un,_n),cn,Fn),dn,En),mn,On),hn,Tn),vn,Nn),gn,jn);var Ln="kit",Dn="kit-duotone",Mn="Kit",zn="Kit Duotone";p(p({},Ln,Mn),Dn,zn);var Rn={classic:{"fa-brands":"fab","fa-duotone":"fad","fa-light":"fal","fa-regular":"far","fa-solid":"fas","fa-thin":"fat"},duotone:{"fa-regular":"fadr","fa-light":"fadl","fa-thin":"fadt"},sharp:{"fa-solid":"fass","fa-regular":"fasr","fa-light":"fasl","fa-thin":"fast"},"sharp-duotone":{"fa-solid":"fasds","fa-regular":"fasdr","fa-light":"fasdl","fa-thin":"fasdt"},slab:{"fa-regular":"faslr"},"slab-press":{"fa-regular":"faslpr"},whiteboard:{"fa-semibold":"fawsb"},thumbprint:{"fa-light":"fatl"},notdog:{"fa-solid":"fans"},"notdog-duo":{"fa-solid":"fands"},etch:{"fa-solid":"faes"},jelly:{"fa-regular":"fajr"},"jelly-fill":{"fa-regular":"fajfr"},"jelly-duo":{"fa-regular":"fajdr"},chisel:{"fa-regular":"facr"},utility:{"fa-semibold":"fausb"},"utility-duo":{"fa-semibold":"faudsb"},"utility-fill":{"fa-semibold":"faufsb"}},$n={classic:["fas","far","fal","fat","fad"],duotone:["fadr","fadl","fadt"],sharp:["fass","fasr","fasl","fast"],"sharp-duotone":["fasds","fasdr","fasdl","fasdt"],slab:["faslr"],"slab-press":["faslpr"],whiteboard:["fawsb"],thumbprint:["fatl"],notdog:["fans"],"notdog-duo":["fands"],etch:["faes"],jelly:["fajr"],"jelly-fill":["fajfr"],"jelly-duo":["fajdr"],chisel:["facr"],utility:["fausb"],"utility-duo":["faudsb"],"utility-fill":["faufsb"]},Ue={classic:{fab:"fa-brands",fad:"fa-duotone",fal:"fa-light",far:"fa-regular",fas:"fa-solid",fat:"fa-thin"},duotone:{fadr:"fa-regular",fadl:"fa-light",fadt:"fa-thin"},sharp:{fass:"fa-solid",fasr:"fa-regular",fasl:"fa-light",fast:"fa-thin"},"sharp-duotone":{fasds:"fa-solid",fasdr:"fa-regular",fasdl:"fa-light",fasdt:"fa-thin"},slab:{faslr:"fa-regular"},"slab-press":{faslpr:"fa-regular"},whiteboard:{fawsb:"fa-semibold"},thumbprint:{fatl:"fa-light"},notdog:{fans:"fa-solid"},"notdog-duo":{fands:"fa-solid"},etch:{faes:"fa-solid"},jelly:{fajr:"fa-regular"},"jelly-fill":{fajfr:"fa-regular"},"jelly-duo":{fajdr:"fa-regular"},chisel:{facr:"fa-regular"},utility:{fausb:"fa-semibold"},"utility-duo":{faudsb:"fa-semibold"},"utility-fill":{faufsb:"fa-semibold"}},Wn=["fa-solid","fa-regular","fa-light","fa-thin","fa-duotone","fa-brands","fa-semibold"],ba=["fa","fas","far","fal","fat","fad","fadr","fadl","fadt","fab","fass","fasr","fasl","fast","fasds","fasdr","fasdl","fasdt","faslr","faslpr","fawsb","fatl","fans","fands","faes","fajr","fajfr","fajdr","facr","fausb","faudsb","faufsb"].concat(qr,Wn),Un=["solid","regular","light","thin","duotone","brands","semibold"],xa=[1,2,3,4,5,6,7,8,9,10],Bn=xa.concat([11,12,13,14,15,16,17,18,19,20]),Hn=["aw","fw","pull-left","pull-right"],Yn=[].concat(L(Object.keys($n)),Un,Hn,["2xs","xs","sm","lg","xl","2xl","beat","border","fade","beat-fade","bounce","flip-both","flip-horizontal","flip-vertical","flip","inverse","layers","layers-bottom-left","layers-bottom-right","layers-counter","layers-text","layers-top-left","layers-top-right","li","pull-end","pull-start","pulse","rotate-180","rotate-270","rotate-90","rotate-by","shake","spin-pulse","spin-reverse","spin","stack-1x","stack-2x","stack","ul","width-auto","width-fixed",ge.GROUP,ge.SWAP_OPACITY,ge.PRIMARY,ge.SECONDARY]).concat(xa.map(function(e){return"".concat(e,"x")})).concat(Bn.map(function(e){return"w-".concat(e)})),Vn={"Font Awesome 5 Free":{900:"fas",400:"far"},"Font Awesome 5 Pro":{900:"fas",400:"far",normal:"far",300:"fal"},"Font Awesome 5 Brands":{400:"fab",normal:"fab"},"Font Awesome 5 Duotone":{900:"fad"}},$="___FONT_AWESOME___",Be=16,wa="fa",ka="svg-inline--fa",J="data-fa-i2svg",He="data-fa-pseudo-element",Gn="data-fa-pseudo-element-pending",it="data-prefix",ot="data-icon",St="fontawesome-i2svg",Kn="async",Xn=["HTML","HEAD","STYLE","SCRIPT"],Sa=["::before","::after",":before",":after"],Aa=(function(){try{return!0}catch{return!1}})();function de(e){return new Proxy(e,{get:function(a,r){return r in a?a[r]:a[C]}})}var Ia=f({},Zt);Ia[C]=f(f(f(f({},{"fa-duotone":"duotone"}),Zt[C]),wt.kit),wt["kit-duotone"]);var Jn=de(Ia),Ye=f({},Ur);Ye[C]=f(f(f(f({},{duotone:"fad"}),Ye[C]),kt.kit),kt["kit-duotone"]);var At=de(Ye),Ve=f({},Ue);Ve[C]=f(f({},Ve[C]),Jr.kit);var st=de(Ve),Ge=f({},Rn);Ge[C]=f(f({},Ge[C]),Kr.kit);de(Ge);var qn=yr,Pa="fa-layers-text",Qn=br,Zn=f({},Rr);de(Zn);var ei=["class","data-prefix","data-icon","data-fa-transform","data-fa-mask"],Le=xr,ti=[].concat(L(Br),L(Yn)),le=H.FontAwesomeConfig||{};function ai(e){var t=w.querySelector("script["+e+"]");if(t)return t.getAttribute(e)}function ri(e){return e===""?!0:e==="false"?!1:e==="true"?!0:e}if(w&&typeof w.querySelector=="function"){var ni=[["data-family-prefix","familyPrefix"],["data-css-prefix","cssPrefix"],["data-family-default","familyDefault"],["data-style-default","styleDefault"],["data-replacement-class","replacementClass"],["data-auto-replace-svg","autoReplaceSvg"],["data-auto-add-css","autoAddCss"],["data-search-pseudo-elements","searchPseudoElements"],["data-search-pseudo-elements-warnings","searchPseudoElementsWarnings"],["data-search-pseudo-elements-full-scan","searchPseudoElementsFullScan"],["data-observe-mutations","observeMutations"],["data-mutate-approach","mutateApproach"],["data-keep-original-source","keepOriginalSource"],["data-measure-performance","measurePerformance"],["data-show-missing-icons","showMissingIcons"]];ni.forEach(function(e){var t=Ae(e,2),a=t[0],r=t[1],n=ri(ai(a));n!=null&&(le[r]=n)})}var Ca={styleDefault:"solid",familyDefault:C,cssPrefix:wa,replacementClass:ka,autoReplaceSvg:!0,autoAddCss:!0,searchPseudoElements:!1,searchPseudoElementsWarnings:!0,searchPseudoElementsFullScan:!1,observeMutations:!0,mutateApproach:"async",keepOriginalSource:!0,measurePerformance:!1,showMissingIcons:!0};le.familyPrefix&&(le.cssPrefix=le.familyPrefix);var re=f(f({},Ca),le);re.autoReplaceSvg||(re.observeMutations=!1);var v={};Object.keys(Ca).forEach(function(e){Object.defineProperty(v,e,{enumerable:!0,set:function(a){re[e]=a,fe.forEach(function(r){return r(v)})},get:function(){return re[e]}})});Object.defineProperty(v,"familyPrefix",{enumerable:!0,set:function(t){re.cssPrefix=t,fe.forEach(function(a){return a(v)})},get:function(){return re.cssPrefix}});H.FontAwesomeConfig=v;var fe=[];function ii(e){return fe.push(e),function(){fe.splice(fe.indexOf(e),1)}}var ee=Be,M={size:16,x:0,y:0,rotate:0,flipX:!1,flipY:!1};function oi(e){if(!(!e||!U)){var t=w.createElement("style");t.setAttribute("type","text/css"),t.innerHTML=e;for(var a=w.head.childNodes,r=null,n=a.length-1;n>-1;n--){var i=a[n],o=(i.tagName||"").toUpperCase();["STYLE","LINK"].indexOf(o)>-1&&(r=i)}return w.head.insertBefore(t,r),e}}var si="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";function It(){for(var e=12,t="";e-- >0;)t+=si[Math.random()*62|0];return t}function ne(e){for(var t=[],a=(e||[]).length>>>0;a--;)t[a]=e[a];return t}function lt(e){return e.classList?ne(e.classList):(e.getAttribute("class")||"").split(" ").filter(function(t){return t})}function _a(e){return"".concat(e).replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/'/g,"&#39;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}function li(e){return Object.keys(e||{}).reduce(function(t,a){return t+"".concat(a,'="').concat(_a(e[a]),'" ')},"").trim()}function Ie(e){return Object.keys(e||{}).reduce(function(t,a){return t+"".concat(a,": ").concat(e[a].trim(),";")},"")}function ft(e){return e.size!==M.size||e.x!==M.x||e.y!==M.y||e.rotate!==M.rotate||e.flipX||e.flipY}function fi(e){var t=e.transform,a=e.containerWidth,r=e.iconWidth,n={transform:"translate(".concat(a/2," 256)")},i="translate(".concat(t.x*32,", ").concat(t.y*32,") "),o="scale(".concat(t.size/16*(t.flipX?-1:1),", ").concat(t.size/16*(t.flipY?-1:1),") "),s="rotate(".concat(t.rotate," 0 0)"),l={transform:"".concat(i," ").concat(o," ").concat(s)},u={transform:"translate(".concat(r/2*-1," -256)")};return{outer:n,inner:l,path:u}}function ui(e){var t=e.transform,a=e.width,r=a===void 0?Be:a,n=e.height,i=n===void 0?Be:n,o="";return Qt?o+="translate(".concat(t.x/ee-r/2,"em, ").concat(t.y/ee-i/2,"em) "):o+="translate(calc(-50% + ".concat(t.x/ee,"em), calc(-50% + ").concat(t.y/ee,"em)) "),o+="scale(".concat(t.size/ee*(t.flipX?-1:1),", ").concat(t.size/ee*(t.flipY?-1:1),") "),o+="rotate(".concat(t.rotate,"deg) "),o}var ci=`:root, :host {
  --fa-font-solid: normal 900 1em/1 "Font Awesome 7 Free";
  --fa-font-regular: normal 400 1em/1 "Font Awesome 7 Free";
  --fa-font-light: normal 300 1em/1 "Font Awesome 7 Pro";
  --fa-font-thin: normal 100 1em/1 "Font Awesome 7 Pro";
  --fa-font-duotone: normal 900 1em/1 "Font Awesome 7 Duotone";
  --fa-font-duotone-regular: normal 400 1em/1 "Font Awesome 7 Duotone";
  --fa-font-duotone-light: normal 300 1em/1 "Font Awesome 7 Duotone";
  --fa-font-duotone-thin: normal 100 1em/1 "Font Awesome 7 Duotone";
  --fa-font-brands: normal 400 1em/1 "Font Awesome 7 Brands";
  --fa-font-sharp-solid: normal 900 1em/1 "Font Awesome 7 Sharp";
  --fa-font-sharp-regular: normal 400 1em/1 "Font Awesome 7 Sharp";
  --fa-font-sharp-light: normal 300 1em/1 "Font Awesome 7 Sharp";
  --fa-font-sharp-thin: normal 100 1em/1 "Font Awesome 7 Sharp";
  --fa-font-sharp-duotone-solid: normal 900 1em/1 "Font Awesome 7 Sharp Duotone";
  --fa-font-sharp-duotone-regular: normal 400 1em/1 "Font Awesome 7 Sharp Duotone";
  --fa-font-sharp-duotone-light: normal 300 1em/1 "Font Awesome 7 Sharp Duotone";
  --fa-font-sharp-duotone-thin: normal 100 1em/1 "Font Awesome 7 Sharp Duotone";
  --fa-font-slab-regular: normal 400 1em/1 "Font Awesome 7 Slab";
  --fa-font-slab-press-regular: normal 400 1em/1 "Font Awesome 7 Slab Press";
  --fa-font-whiteboard-semibold: normal 600 1em/1 "Font Awesome 7 Whiteboard";
  --fa-font-thumbprint-light: normal 300 1em/1 "Font Awesome 7 Thumbprint";
  --fa-font-notdog-solid: normal 900 1em/1 "Font Awesome 7 Notdog";
  --fa-font-notdog-duo-solid: normal 900 1em/1 "Font Awesome 7 Notdog Duo";
  --fa-font-etch-solid: normal 900 1em/1 "Font Awesome 7 Etch";
  --fa-font-jelly-regular: normal 400 1em/1 "Font Awesome 7 Jelly";
  --fa-font-jelly-fill-regular: normal 400 1em/1 "Font Awesome 7 Jelly Fill";
  --fa-font-jelly-duo-regular: normal 400 1em/1 "Font Awesome 7 Jelly Duo";
  --fa-font-chisel-regular: normal 400 1em/1 "Font Awesome 7 Chisel";
  --fa-font-utility-semibold: normal 600 1em/1 "Font Awesome 7 Utility";
  --fa-font-utility-duo-semibold: normal 600 1em/1 "Font Awesome 7 Utility Duo";
  --fa-font-utility-fill-semibold: normal 600 1em/1 "Font Awesome 7 Utility Fill";
}

.svg-inline--fa {
  box-sizing: content-box;
  display: var(--fa-display, inline-block);
  height: 1em;
  overflow: visible;
  vertical-align: -0.125em;
  width: var(--fa-width, 1.25em);
}
.svg-inline--fa.fa-2xs {
  vertical-align: 0.1em;
}
.svg-inline--fa.fa-xs {
  vertical-align: 0em;
}
.svg-inline--fa.fa-sm {
  vertical-align: -0.0714285714em;
}
.svg-inline--fa.fa-lg {
  vertical-align: -0.2em;
}
.svg-inline--fa.fa-xl {
  vertical-align: -0.25em;
}
.svg-inline--fa.fa-2xl {
  vertical-align: -0.3125em;
}
.svg-inline--fa.fa-pull-left,
.svg-inline--fa .fa-pull-start {
  float: inline-start;
  margin-inline-end: var(--fa-pull-margin, 0.3em);
}
.svg-inline--fa.fa-pull-right,
.svg-inline--fa .fa-pull-end {
  float: inline-end;
  margin-inline-start: var(--fa-pull-margin, 0.3em);
}
.svg-inline--fa.fa-li {
  width: var(--fa-li-width, 2em);
  inset-inline-start: calc(-1 * var(--fa-li-width, 2em));
  inset-block-start: 0.25em; /* syncing vertical alignment with Web Font rendering */
}

.fa-layers-counter, .fa-layers-text {
  display: inline-block;
  position: absolute;
  text-align: center;
}

.fa-layers {
  display: inline-block;
  height: 1em;
  position: relative;
  text-align: center;
  vertical-align: -0.125em;
  width: var(--fa-width, 1.25em);
}
.fa-layers .svg-inline--fa {
  inset: 0;
  margin: auto;
  position: absolute;
  transform-origin: center center;
}

.fa-layers-text {
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  transform-origin: center center;
}

.fa-layers-counter {
  background-color: var(--fa-counter-background-color, #ff253a);
  border-radius: var(--fa-counter-border-radius, 1em);
  box-sizing: border-box;
  color: var(--fa-inverse, #fff);
  line-height: var(--fa-counter-line-height, 1);
  max-width: var(--fa-counter-max-width, 5em);
  min-width: var(--fa-counter-min-width, 1.5em);
  overflow: hidden;
  padding: var(--fa-counter-padding, 0.25em 0.5em);
  right: var(--fa-right, 0);
  text-overflow: ellipsis;
  top: var(--fa-top, 0);
  transform: scale(var(--fa-counter-scale, 0.25));
  transform-origin: top right;
}

.fa-layers-bottom-right {
  bottom: var(--fa-bottom, 0);
  right: var(--fa-right, 0);
  top: auto;
  transform: scale(var(--fa-layers-scale, 0.25));
  transform-origin: bottom right;
}

.fa-layers-bottom-left {
  bottom: var(--fa-bottom, 0);
  left: var(--fa-left, 0);
  right: auto;
  top: auto;
  transform: scale(var(--fa-layers-scale, 0.25));
  transform-origin: bottom left;
}

.fa-layers-top-right {
  top: var(--fa-top, 0);
  right: var(--fa-right, 0);
  transform: scale(var(--fa-layers-scale, 0.25));
  transform-origin: top right;
}

.fa-layers-top-left {
  left: var(--fa-left, 0);
  right: auto;
  top: var(--fa-top, 0);
  transform: scale(var(--fa-layers-scale, 0.25));
  transform-origin: top left;
}

.fa-1x {
  font-size: 1em;
}

.fa-2x {
  font-size: 2em;
}

.fa-3x {
  font-size: 3em;
}

.fa-4x {
  font-size: 4em;
}

.fa-5x {
  font-size: 5em;
}

.fa-6x {
  font-size: 6em;
}

.fa-7x {
  font-size: 7em;
}

.fa-8x {
  font-size: 8em;
}

.fa-9x {
  font-size: 9em;
}

.fa-10x {
  font-size: 10em;
}

.fa-2xs {
  font-size: calc(10 / 16 * 1em); /* converts a 10px size into an em-based value that's relative to the scale's 16px base */
  line-height: calc(1 / 10 * 1em); /* sets the line-height of the icon back to that of it's parent */
  vertical-align: calc((6 / 10 - 0.375) * 1em); /* vertically centers the icon taking into account the surrounding text's descender */
}

.fa-xs {
  font-size: calc(12 / 16 * 1em); /* converts a 12px size into an em-based value that's relative to the scale's 16px base */
  line-height: calc(1 / 12 * 1em); /* sets the line-height of the icon back to that of it's parent */
  vertical-align: calc((6 / 12 - 0.375) * 1em); /* vertically centers the icon taking into account the surrounding text's descender */
}

.fa-sm {
  font-size: calc(14 / 16 * 1em); /* converts a 14px size into an em-based value that's relative to the scale's 16px base */
  line-height: calc(1 / 14 * 1em); /* sets the line-height of the icon back to that of it's parent */
  vertical-align: calc((6 / 14 - 0.375) * 1em); /* vertically centers the icon taking into account the surrounding text's descender */
}

.fa-lg {
  font-size: calc(20 / 16 * 1em); /* converts a 20px size into an em-based value that's relative to the scale's 16px base */
  line-height: calc(1 / 20 * 1em); /* sets the line-height of the icon back to that of it's parent */
  vertical-align: calc((6 / 20 - 0.375) * 1em); /* vertically centers the icon taking into account the surrounding text's descender */
}

.fa-xl {
  font-size: calc(24 / 16 * 1em); /* converts a 24px size into an em-based value that's relative to the scale's 16px base */
  line-height: calc(1 / 24 * 1em); /* sets the line-height of the icon back to that of it's parent */
  vertical-align: calc((6 / 24 - 0.375) * 1em); /* vertically centers the icon taking into account the surrounding text's descender */
}

.fa-2xl {
  font-size: calc(32 / 16 * 1em); /* converts a 32px size into an em-based value that's relative to the scale's 16px base */
  line-height: calc(1 / 32 * 1em); /* sets the line-height of the icon back to that of it's parent */
  vertical-align: calc((6 / 32 - 0.375) * 1em); /* vertically centers the icon taking into account the surrounding text's descender */
}

.fa-width-auto {
  --fa-width: auto;
}

.fa-fw,
.fa-width-fixed {
  --fa-width: 1.25em;
}

.fa-ul {
  list-style-type: none;
  margin-inline-start: var(--fa-li-margin, 2.5em);
  padding-inline-start: 0;
}
.fa-ul > li {
  position: relative;
}

.fa-li {
  inset-inline-start: calc(-1 * var(--fa-li-width, 2em));
  position: absolute;
  text-align: center;
  width: var(--fa-li-width, 2em);
  line-height: inherit;
}

/* Heads Up: Bordered Icons will not be supported in the future!
  - This feature will be deprecated in the next major release of Font Awesome (v8)!
  - You may continue to use it in this version *v7), but it will not be supported in Font Awesome v8.
*/
/* Notes:
* --@{v.$css-prefix}-border-width = 1/16 by default (to render as ~1px based on a 16px default font-size)
* --@{v.$css-prefix}-border-padding =
  ** 3/16 for vertical padding (to give ~2px of vertical whitespace around an icon considering it's vertical alignment)
  ** 4/16 for horizontal padding (to give ~4px of horizontal whitespace around an icon)
*/
.fa-border {
  border-color: var(--fa-border-color, #eee);
  border-radius: var(--fa-border-radius, 0.1em);
  border-style: var(--fa-border-style, solid);
  border-width: var(--fa-border-width, 0.0625em);
  box-sizing: var(--fa-border-box-sizing, content-box);
  padding: var(--fa-border-padding, 0.1875em 0.25em);
}

.fa-pull-left,
.fa-pull-start {
  float: inline-start;
  margin-inline-end: var(--fa-pull-margin, 0.3em);
}

.fa-pull-right,
.fa-pull-end {
  float: inline-end;
  margin-inline-start: var(--fa-pull-margin, 0.3em);
}

.fa-beat {
  animation-name: fa-beat;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-in-out);
}

.fa-bounce {
  animation-name: fa-bounce;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, cubic-bezier(0.28, 0.84, 0.42, 1));
}

.fa-fade {
  animation-name: fa-fade;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, cubic-bezier(0.4, 0, 0.6, 1));
}

.fa-beat-fade {
  animation-name: fa-beat-fade;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, cubic-bezier(0.4, 0, 0.6, 1));
}

.fa-flip {
  animation-name: fa-flip;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-in-out);
}

.fa-shake {
  animation-name: fa-shake;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, linear);
}

.fa-spin {
  animation-name: fa-spin;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 2s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, linear);
}

.fa-spin-reverse {
  --fa-animation-direction: reverse;
}

.fa-pulse,
.fa-spin-pulse {
  animation-name: fa-spin;
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, steps(8));
}

@media (prefers-reduced-motion: reduce) {
  .fa-beat,
  .fa-bounce,
  .fa-fade,
  .fa-beat-fade,
  .fa-flip,
  .fa-pulse,
  .fa-shake,
  .fa-spin,
  .fa-spin-pulse {
    animation: none !important;
    transition: none !important;
  }
}
@keyframes fa-beat {
  0%, 90% {
    transform: scale(1);
  }
  45% {
    transform: scale(var(--fa-beat-scale, 1.25));
  }
}
@keyframes fa-bounce {
  0% {
    transform: scale(1, 1) translateY(0);
  }
  10% {
    transform: scale(var(--fa-bounce-start-scale-x, 1.1), var(--fa-bounce-start-scale-y, 0.9)) translateY(0);
  }
  30% {
    transform: scale(var(--fa-bounce-jump-scale-x, 0.9), var(--fa-bounce-jump-scale-y, 1.1)) translateY(var(--fa-bounce-height, -0.5em));
  }
  50% {
    transform: scale(var(--fa-bounce-land-scale-x, 1.05), var(--fa-bounce-land-scale-y, 0.95)) translateY(0);
  }
  57% {
    transform: scale(1, 1) translateY(var(--fa-bounce-rebound, -0.125em));
  }
  64% {
    transform: scale(1, 1) translateY(0);
  }
  100% {
    transform: scale(1, 1) translateY(0);
  }
}
@keyframes fa-fade {
  50% {
    opacity: var(--fa-fade-opacity, 0.4);
  }
}
@keyframes fa-beat-fade {
  0%, 100% {
    opacity: var(--fa-beat-fade-opacity, 0.4);
    transform: scale(1);
  }
  50% {
    opacity: 1;
    transform: scale(var(--fa-beat-fade-scale, 1.125));
  }
}
@keyframes fa-flip {
  50% {
    transform: rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), var(--fa-flip-angle, -180deg));
  }
}
@keyframes fa-shake {
  0% {
    transform: rotate(-15deg);
  }
  4% {
    transform: rotate(15deg);
  }
  8%, 24% {
    transform: rotate(-18deg);
  }
  12%, 28% {
    transform: rotate(18deg);
  }
  16% {
    transform: rotate(-22deg);
  }
  20% {
    transform: rotate(22deg);
  }
  32% {
    transform: rotate(-12deg);
  }
  36% {
    transform: rotate(12deg);
  }
  40%, 100% {
    transform: rotate(0deg);
  }
}
@keyframes fa-spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
.fa-rotate-90 {
  transform: rotate(90deg);
}

.fa-rotate-180 {
  transform: rotate(180deg);
}

.fa-rotate-270 {
  transform: rotate(270deg);
}

.fa-flip-horizontal {
  transform: scale(-1, 1);
}

.fa-flip-vertical {
  transform: scale(1, -1);
}

.fa-flip-both,
.fa-flip-horizontal.fa-flip-vertical {
  transform: scale(-1, -1);
}

.fa-rotate-by {
  transform: rotate(var(--fa-rotate-angle, 0));
}

.svg-inline--fa .fa-primary {
  fill: var(--fa-primary-color, currentColor);
  opacity: var(--fa-primary-opacity, 1);
}

.svg-inline--fa .fa-secondary {
  fill: var(--fa-secondary-color, currentColor);
  opacity: var(--fa-secondary-opacity, 0.4);
}

.svg-inline--fa.fa-swap-opacity .fa-primary {
  opacity: var(--fa-secondary-opacity, 0.4);
}

.svg-inline--fa.fa-swap-opacity .fa-secondary {
  opacity: var(--fa-primary-opacity, 1);
}

.svg-inline--fa mask .fa-primary,
.svg-inline--fa mask .fa-secondary {
  fill: black;
}

.svg-inline--fa.fa-inverse {
  fill: var(--fa-inverse, #fff);
}

.fa-stack {
  display: inline-block;
  height: 2em;
  line-height: 2em;
  position: relative;
  vertical-align: middle;
  width: 2.5em;
}

.fa-inverse {
  color: var(--fa-inverse, #fff);
}

.svg-inline--fa.fa-stack-1x {
  --fa-width: 1.25em;
  height: 1em;
  width: var(--fa-width);
}
.svg-inline--fa.fa-stack-2x {
  --fa-width: 2.5em;
  height: 2em;
  width: var(--fa-width);
}

.fa-stack-1x,
.fa-stack-2x {
  inset: 0;
  margin: auto;
  position: absolute;
  z-index: var(--fa-stack-z-index, auto);
}`;function Fa(){var e=wa,t=ka,a=v.cssPrefix,r=v.replacementClass,n=ci;if(a!==e||r!==t){var i=new RegExp("\\.".concat(e,"\\-"),"g"),o=new RegExp("\\--".concat(e,"\\-"),"g"),s=new RegExp("\\.".concat(t),"g");n=n.replace(i,".".concat(a,"-")).replace(o,"--".concat(a,"-")).replace(s,".".concat(r))}return n}var Pt=!1;function De(){v.autoAddCss&&!Pt&&(oi(Fa()),Pt=!0)}var di={mixout:function(){return{dom:{css:Fa,insertCss:De}}},hooks:function(){return{beforeDOMElementCreation:function(){De()},beforeI2svg:function(){De()}}}},W=H||{};W[$]||(W[$]={});W[$].styles||(W[$].styles={});W[$].hooks||(W[$].hooks={});W[$].shims||(W[$].shims=[]);var j=W[$],Ea=[],Oa=function(){w.removeEventListener("DOMContentLoaded",Oa),we=1,Ea.map(function(t){return t()})},we=!1;U&&(we=(w.documentElement.doScroll?/^loaded|^c/:/^loaded|^i|^c/).test(w.readyState),we||w.addEventListener("DOMContentLoaded",Oa));function mi(e){U&&(we?setTimeout(e,0):Ea.push(e))}function me(e){var t=e.tag,a=e.attributes,r=a===void 0?{}:a,n=e.children,i=n===void 0?[]:n;return typeof e=="string"?_a(e):"<".concat(t," ").concat(li(r),">").concat(i.map(me).join(""),"</").concat(t,">")}function Ct(e,t,a){if(e&&e[t]&&e[t][a])return{prefix:t,iconName:a,icon:e[t][a]}}var Me=function(t,a,r,n){var i=Object.keys(t),o=i.length,s=a,l,u,d;for(r===void 0?(l=1,d=t[i[0]]):(l=0,d=r);l<o;l++)u=i[l],d=s(d,t[u],u,t);return d};function Ta(e){return L(e).length!==1?null:e.codePointAt(0).toString(16)}function _t(e){return Object.keys(e).reduce(function(t,a){var r=e[a],n=!!r.icon;return n?t[r.iconName]=r.icon:t[a]=r,t},{})}function Ke(e,t){var a=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{},r=a.skipHooks,n=r===void 0?!1:r,i=_t(t);typeof j.hooks.addPack=="function"&&!n?j.hooks.addPack(e,_t(t)):j.styles[e]=f(f({},j.styles[e]||{}),i),e==="fas"&&Ke("fa",t)}var ue=j.styles,hi=j.shims,Na=Object.keys(st),vi=Na.reduce(function(e,t){return e[t]=Object.keys(st[t]),e},{}),ut=null,ja={},La={},Da={},Ma={},za={};function gi(e){return~ti.indexOf(e)}function pi(e,t){var a=t.split("-"),r=a[0],n=a.slice(1).join("-");return r===e&&n!==""&&!gi(n)?n:null}var Ra=function(){var t=function(i){return Me(ue,function(o,s,l){return o[l]=Me(s,i,{}),o},{})};ja=t(function(n,i,o){if(i[3]&&(n[i[3]]=o),i[2]){var s=i[2].filter(function(l){return typeof l=="number"});s.forEach(function(l){n[l.toString(16)]=o})}return n}),La=t(function(n,i,o){if(n[o]=o,i[2]){var s=i[2].filter(function(l){return typeof l=="string"});s.forEach(function(l){n[l]=o})}return n}),za=t(function(n,i,o){var s=i[2];return n[o]=o,s.forEach(function(l){n[l]=o}),n});var a="far"in ue||v.autoFetchSvg,r=Me(hi,function(n,i){var o=i[0],s=i[1],l=i[2];return s==="far"&&!a&&(s="fas"),typeof o=="string"&&(n.names[o]={prefix:s,iconName:l}),typeof o=="number"&&(n.unicodes[o.toString(16)]={prefix:s,iconName:l}),n},{names:{},unicodes:{}});Da=r.names,Ma=r.unicodes,ut=Pe(v.styleDefault,{family:v.familyDefault})};ii(function(e){ut=Pe(e.styleDefault,{family:v.familyDefault})});Ra();function ct(e,t){return(ja[e]||{})[t]}function yi(e,t){return(La[e]||{})[t]}function X(e,t){return(za[e]||{})[t]}function $a(e){return Da[e]||{prefix:null,iconName:null}}function bi(e){var t=Ma[e],a=ct("fas",e);return t||(a?{prefix:"fas",iconName:a}:null)||{prefix:null,iconName:null}}function Y(){return ut}var Wa=function(){return{prefix:null,iconName:null,rest:[]}};function xi(e){var t=C,a=Na.reduce(function(r,n){return r[n]="".concat(v.cssPrefix,"-").concat(n),r},{});return pa.forEach(function(r){(e.includes(a[r])||e.some(function(n){return vi[r].includes(n)}))&&(t=r)}),t}function Pe(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},a=t.family,r=a===void 0?C:a,n=Jn[r][e];if(r===ce&&!e)return"fad";var i=At[r][e]||At[r][n],o=e in j.styles?e:null,s=i||o||null;return s}function wi(e){var t=[],a=null;return e.forEach(function(r){var n=pi(v.cssPrefix,r);n?a=n:r&&t.push(r)}),{iconName:a,rest:t}}function Ft(e){return e.sort().filter(function(t,a,r){return r.indexOf(t)===a})}var Et=ba.concat(ya);function Ce(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},a=t.skipLookups,r=a===void 0?!1:a,n=null,i=Ft(e.filter(function(g){return Et.includes(g)})),o=Ft(e.filter(function(g){return!Et.includes(g)})),s=i.filter(function(g){return n=g,!ea.includes(g)}),l=Ae(s,1),u=l[0],d=u===void 0?null:u,h=xi(i),y=f(f({},wi(o)),{},{prefix:Pe(d,{family:h})});return f(f(f({},y),Ii({values:e,family:h,styles:ue,config:v,canonical:y,givenPrefix:n})),ki(r,n,y))}function ki(e,t,a){var r=a.prefix,n=a.iconName;if(e||!r||!n)return{prefix:r,iconName:n};var i=t==="fa"?$a(n):{},o=X(r,n);return n=i.iconName||o||n,r=i.prefix||r,r==="far"&&!ue.far&&ue.fas&&!v.autoFetchSvg&&(r="fas"),{prefix:r,iconName:n}}var Si=pa.filter(function(e){return e!==C||e!==ce}),Ai=Object.keys(Ue).filter(function(e){return e!==C}).map(function(e){return Object.keys(Ue[e])}).flat();function Ii(e){var t=e.values,a=e.family,r=e.canonical,n=e.givenPrefix,i=n===void 0?"":n,o=e.styles,s=o===void 0?{}:o,l=e.config,u=l===void 0?{}:l,d=a===ce,h=t.includes("fa-duotone")||t.includes("fad"),y=u.familyDefault==="duotone",g=r.prefix==="fad"||r.prefix==="fa-duotone";if(!d&&(h||y||g)&&(r.prefix="fad"),(t.includes("fa-brands")||t.includes("fab"))&&(r.prefix="fab"),!r.prefix&&Si.includes(a)){var S=Object.keys(s).find(function(A){return Ai.includes(A)});if(S||u.autoFetchSvg){var b=Wr.get(a).defaultShortPrefixId;r.prefix=b,r.iconName=X(r.prefix,r.iconName)||r.iconName}}return(r.prefix==="fa"||i==="fa")&&(r.prefix=Y()||"fas"),r}var Pi=(function(){function e(){fr(this,e),this.definitions={}}return cr(e,[{key:"add",value:function(){for(var a=this,r=arguments.length,n=new Array(r),i=0;i<r;i++)n[i]=arguments[i];var o=n.reduce(this._pullDefinitions,{});Object.keys(o).forEach(function(s){a.definitions[s]=f(f({},a.definitions[s]||{}),o[s]),Ke(s,o[s]);var l=st[C][s];l&&Ke(l,o[s]),Ra()})}},{key:"reset",value:function(){this.definitions={}}},{key:"_pullDefinitions",value:function(a,r){var n=r.prefix&&r.iconName&&r.icon?{0:r}:r;return Object.keys(n).map(function(i){var o=n[i],s=o.prefix,l=o.iconName,u=o.icon,d=u[2];a[s]||(a[s]={}),d.length>0&&d.forEach(function(h){typeof h=="string"&&(a[s][h]=u)}),a[s][l]=u}),a}}])})(),Ot=[],te={},ae={},Ci=Object.keys(ae);function _i(e,t){var a=t.mixoutsTo;return Ot=e,te={},Object.keys(ae).forEach(function(r){Ci.indexOf(r)===-1&&delete ae[r]}),Ot.forEach(function(r){var n=r.mixout?r.mixout():{};if(Object.keys(n).forEach(function(o){typeof n[o]=="function"&&(a[o]=n[o]),xe(n[o])==="object"&&Object.keys(n[o]).forEach(function(s){a[o]||(a[o]={}),a[o][s]=n[o][s]})}),r.hooks){var i=r.hooks();Object.keys(i).forEach(function(o){te[o]||(te[o]=[]),te[o].push(i[o])})}r.provides&&r.provides(ae)}),a}function Xe(e,t){for(var a=arguments.length,r=new Array(a>2?a-2:0),n=2;n<a;n++)r[n-2]=arguments[n];var i=te[e]||[];return i.forEach(function(o){t=o.apply(null,[t].concat(r))}),t}function q(e){for(var t=arguments.length,a=new Array(t>1?t-1:0),r=1;r<t;r++)a[r-1]=arguments[r];var n=te[e]||[];n.forEach(function(i){i.apply(null,a)})}function V(){var e=arguments[0],t=Array.prototype.slice.call(arguments,1);return ae[e]?ae[e].apply(null,t):void 0}function Je(e){e.prefix==="fa"&&(e.prefix="fas");var t=e.iconName,a=e.prefix||Y();if(t)return t=X(a,t)||t,Ct(Ua.definitions,a,t)||Ct(j.styles,a,t)}var Ua=new Pi,Fi=function(){v.autoReplaceSvg=!1,v.observeMutations=!1,q("noAuto")},Ei={i2svg:function(){var t=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{};return U?(q("beforeI2svg",t),V("pseudoElements2svg",t),V("i2svg",t)):Promise.reject(new Error("Operation requires a DOM of some kind."))},watch:function(){var t=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{},a=t.autoReplaceSvgRoot;v.autoReplaceSvg===!1&&(v.autoReplaceSvg=!0),v.observeMutations=!0,mi(function(){Ti({autoReplaceSvgRoot:a}),q("watch",t)})}},Oi={icon:function(t){if(t===null)return null;if(xe(t)==="object"&&t.prefix&&t.iconName)return{prefix:t.prefix,iconName:X(t.prefix,t.iconName)||t.iconName};if(Array.isArray(t)&&t.length===2){var a=t[1].indexOf("fa-")===0?t[1].slice(3):t[1],r=Pe(t[0]);return{prefix:r,iconName:X(r,a)||a}}if(typeof t=="string"&&(t.indexOf("".concat(v.cssPrefix,"-"))>-1||t.match(qn))){var n=Ce(t.split(" "),{skipLookups:!0});return{prefix:n.prefix||Y(),iconName:X(n.prefix,n.iconName)||n.iconName}}if(typeof t=="string"){var i=Y();return{prefix:i,iconName:X(i,t)||t}}}},T={noAuto:Fi,config:v,dom:Ei,parse:Oi,library:Ua,findIconDefinition:Je,toHtml:me},Ti=function(){var t=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{},a=t.autoReplaceSvgRoot,r=a===void 0?w:a;(Object.keys(j.styles).length>0||v.autoFetchSvg)&&U&&v.autoReplaceSvg&&T.dom.i2svg({node:r})};function _e(e,t){return Object.defineProperty(e,"abstract",{get:t}),Object.defineProperty(e,"html",{get:function(){return e.abstract.map(function(r){return me(r)})}}),Object.defineProperty(e,"node",{get:function(){if(U){var r=w.createElement("div");return r.innerHTML=e.html,r.children}}}),e}function Ni(e){var t=e.children,a=e.main,r=e.mask,n=e.attributes,i=e.styles,o=e.transform;if(ft(o)&&a.found&&!r.found){var s=a.width,l=a.height,u={x:s/l/2,y:.5};n.style=Ie(f(f({},i),{},{"transform-origin":"".concat(u.x+o.x/16,"em ").concat(u.y+o.y/16,"em")}))}return[{tag:"svg",attributes:n,children:t}]}function ji(e){var t=e.prefix,a=e.iconName,r=e.children,n=e.attributes,i=e.symbol,o=i===!0?"".concat(t,"-").concat(v.cssPrefix,"-").concat(a):i;return[{tag:"svg",attributes:{style:"display: none;"},children:[{tag:"symbol",attributes:f(f({},n),{},{id:o}),children:r}]}]}function Li(e){var t=["aria-label","aria-labelledby","title","role"];return t.some(function(a){return a in e})}function dt(e){var t=e.icons,a=t.main,r=t.mask,n=e.prefix,i=e.iconName,o=e.transform,s=e.symbol,l=e.maskId,u=e.extra,d=e.watchable,h=d===void 0?!1:d,y=r.found?r:a,g=y.width,S=y.height,b=[v.replacementClass,i?"".concat(v.cssPrefix,"-").concat(i):""].filter(function(F){return u.classes.indexOf(F)===-1}).filter(function(F){return F!==""||!!F}).concat(u.classes).join(" "),A={children:[],attributes:f(f({},u.attributes),{},{"data-prefix":n,"data-icon":i,class:b,role:u.attributes.role||"img",viewBox:"0 0 ".concat(g," ").concat(S)})};!Li(u.attributes)&&!u.attributes["aria-hidden"]&&(A.attributes["aria-hidden"]="true"),h&&(A.attributes[J]="");var m=f(f({},A),{},{prefix:n,iconName:i,main:a,mask:r,maskId:l,transform:o,symbol:s,styles:f({},u.styles)}),c=r.found&&a.found?V("generateAbstractMask",m)||{children:[],attributes:{}}:V("generateAbstractIcon",m)||{children:[],attributes:{}},x=c.children,I=c.attributes;return m.children=x,m.attributes=I,s?ji(m):Ni(m)}function Tt(e){var t=e.content,a=e.width,r=e.height,n=e.transform,i=e.extra,o=e.watchable,s=o===void 0?!1:o,l=f(f({},i.attributes),{},{class:i.classes.join(" ")});s&&(l[J]="");var u=f({},i.styles);ft(n)&&(u.transform=ui({transform:n,width:a,height:r}),u["-webkit-transform"]=u.transform);var d=Ie(u);d.length>0&&(l.style=d);var h=[];return h.push({tag:"span",attributes:l,children:[t]}),h}function Di(e){var t=e.content,a=e.extra,r=f(f({},a.attributes),{},{class:a.classes.join(" ")}),n=Ie(a.styles);n.length>0&&(r.style=n);var i=[];return i.push({tag:"span",attributes:r,children:[t]}),i}var ze=j.styles;function qe(e){var t=e[0],a=e[1],r=e.slice(4),n=Ae(r,1),i=n[0],o=null;return Array.isArray(i)?o={tag:"g",attributes:{class:"".concat(v.cssPrefix,"-").concat(Le.GROUP)},children:[{tag:"path",attributes:{class:"".concat(v.cssPrefix,"-").concat(Le.SECONDARY),fill:"currentColor",d:i[0]}},{tag:"path",attributes:{class:"".concat(v.cssPrefix,"-").concat(Le.PRIMARY),fill:"currentColor",d:i[1]}}]}:o={tag:"path",attributes:{fill:"currentColor",d:i}},{found:!0,width:t,height:a,icon:o}}var Mi={found:!1,width:512,height:512};function zi(e,t){!Aa&&!v.showMissingIcons&&e&&console.error('Icon with name "'.concat(e,'" and prefix "').concat(t,'" is missing.'))}function Qe(e,t){var a=t;return t==="fa"&&v.styleDefault!==null&&(t=Y()),new Promise(function(r,n){if(a==="fa"){var i=$a(e)||{};e=i.iconName||e,t=i.prefix||t}if(e&&t&&ze[t]&&ze[t][e]){var o=ze[t][e];return r(qe(o))}zi(e,t),r(f(f({},Mi),{},{icon:v.showMissingIcons&&e?V("missingIconAbstract")||{}:{}}))})}var Nt=function(){},Ze=v.measurePerformance&&ve&&ve.mark&&ve.measure?ve:{mark:Nt,measure:Nt},se='FA "7.1.0"',Ri=function(t){return Ze.mark("".concat(se," ").concat(t," begins")),function(){return Ba(t)}},Ba=function(t){Ze.mark("".concat(se," ").concat(t," ends")),Ze.measure("".concat(se," ").concat(t),"".concat(se," ").concat(t," begins"),"".concat(se," ").concat(t," ends"))},mt={begin:Ri,end:Ba},ye=function(){};function jt(e){var t=e.getAttribute?e.getAttribute(J):null;return typeof t=="string"}function $i(e){var t=e.getAttribute?e.getAttribute(it):null,a=e.getAttribute?e.getAttribute(ot):null;return t&&a}function Wi(e){return e&&e.classList&&e.classList.contains&&e.classList.contains(v.replacementClass)}function Ui(){if(v.autoReplaceSvg===!0)return be.replace;var e=be[v.autoReplaceSvg];return e||be.replace}function Bi(e){return w.createElementNS("http://www.w3.org/2000/svg",e)}function Hi(e){return w.createElement(e)}function Ha(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},a=t.ceFn,r=a===void 0?e.tag==="svg"?Bi:Hi:a;if(typeof e=="string")return w.createTextNode(e);var n=r(e.tag);Object.keys(e.attributes||[]).forEach(function(o){n.setAttribute(o,e.attributes[o])});var i=e.children||[];return i.forEach(function(o){n.appendChild(Ha(o,{ceFn:r}))}),n}function Yi(e){var t=" ".concat(e.outerHTML," ");return t="".concat(t,"Font Awesome fontawesome.com "),t}var be={replace:function(t){var a=t[0];if(a.parentNode)if(t[1].forEach(function(n){a.parentNode.insertBefore(Ha(n),a)}),a.getAttribute(J)===null&&v.keepOriginalSource){var r=w.createComment(Yi(a));a.parentNode.replaceChild(r,a)}else a.remove()},nest:function(t){var a=t[0],r=t[1];if(~lt(a).indexOf(v.replacementClass))return be.replace(t);var n=new RegExp("".concat(v.cssPrefix,"-.*"));if(delete r[0].attributes.id,r[0].attributes.class){var i=r[0].attributes.class.split(" ").reduce(function(s,l){return l===v.replacementClass||l.match(n)?s.toSvg.push(l):s.toNode.push(l),s},{toNode:[],toSvg:[]});r[0].attributes.class=i.toSvg.join(" "),i.toNode.length===0?a.removeAttribute("class"):a.setAttribute("class",i.toNode.join(" "))}var o=r.map(function(s){return me(s)}).join(`
`);a.setAttribute(J,""),a.innerHTML=o}};function Lt(e){e()}function Ya(e,t){var a=typeof t=="function"?t:ye;if(e.length===0)a();else{var r=Lt;v.mutateApproach===Kn&&(r=H.requestAnimationFrame||Lt),r(function(){var n=Ui(),i=mt.begin("mutate");e.map(n),i(),a()})}}var ht=!1;function Va(){ht=!0}function et(){ht=!1}var ke=null;function Dt(e){if(xt&&v.observeMutations){var t=e.treeCallback,a=t===void 0?ye:t,r=e.nodeCallback,n=r===void 0?ye:r,i=e.pseudoElementsCallback,o=i===void 0?ye:i,s=e.observeMutationsRoot,l=s===void 0?w:s;ke=new xt(function(u){if(!ht){var d=Y();ne(u).forEach(function(h){if(h.type==="childList"&&h.addedNodes.length>0&&!jt(h.addedNodes[0])&&(v.searchPseudoElements&&o(h.target),a(h.target)),h.type==="attributes"&&h.target.parentNode&&v.searchPseudoElements&&o([h.target],!0),h.type==="attributes"&&jt(h.target)&&~ei.indexOf(h.attributeName))if(h.attributeName==="class"&&$i(h.target)){var y=Ce(lt(h.target)),g=y.prefix,S=y.iconName;h.target.setAttribute(it,g||d),S&&h.target.setAttribute(ot,S)}else Wi(h.target)&&n(h.target)})}}),U&&ke.observe(l,{childList:!0,attributes:!0,characterData:!0,subtree:!0})}}function Vi(){ke&&ke.disconnect()}function Gi(e){var t=e.getAttribute("style"),a=[];return t&&(a=t.split(";").reduce(function(r,n){var i=n.split(":"),o=i[0],s=i.slice(1);return o&&s.length>0&&(r[o]=s.join(":").trim()),r},{})),a}function Ki(e){var t=e.getAttribute("data-prefix"),a=e.getAttribute("data-icon"),r=e.innerText!==void 0?e.innerText.trim():"",n=Ce(lt(e));return n.prefix||(n.prefix=Y()),t&&a&&(n.prefix=t,n.iconName=a),n.iconName&&n.prefix||(n.prefix&&r.length>0&&(n.iconName=yi(n.prefix,e.innerText)||ct(n.prefix,Ta(e.innerText))),!n.iconName&&v.autoFetchSvg&&e.firstChild&&e.firstChild.nodeType===Node.TEXT_NODE&&(n.iconName=e.firstChild.data)),n}function Xi(e){var t=ne(e.attributes).reduce(function(a,r){return a.name!=="class"&&a.name!=="style"&&(a[r.name]=r.value),a},{});return t}function Ji(){return{iconName:null,prefix:null,transform:M,symbol:!1,mask:{iconName:null,prefix:null,rest:[]},maskId:null,extra:{classes:[],styles:{},attributes:{}}}}function Mt(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{styleParser:!0},a=Ki(e),r=a.iconName,n=a.prefix,i=a.rest,o=Xi(e),s=Xe("parseNodeAttributes",{},e),l=t.styleParser?Gi(e):[];return f({iconName:r,prefix:n,transform:M,mask:{iconName:null,prefix:null,rest:[]},maskId:null,symbol:!1,extra:{classes:i,styles:l,attributes:o}},s)}var qi=j.styles;function Ga(e){var t=v.autoReplaceSvg==="nest"?Mt(e,{styleParser:!1}):Mt(e);return~t.extra.classes.indexOf(Pa)?V("generateLayersText",e,t):V("generateSvgReplacementMutation",e,t)}function Qi(){return[].concat(L(ya),L(ba))}function zt(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:null;if(!U)return Promise.resolve();var a=w.documentElement.classList,r=function(h){return a.add("".concat(St,"-").concat(h))},n=function(h){return a.remove("".concat(St,"-").concat(h))},i=v.autoFetchSvg?Qi():ea.concat(Object.keys(qi));i.includes("fa")||i.push("fa");var o=[".".concat(Pa,":not([").concat(J,"])")].concat(i.map(function(d){return".".concat(d,":not([").concat(J,"])")})).join(", ");if(o.length===0)return Promise.resolve();var s=[];try{s=ne(e.querySelectorAll(o))}catch{}if(s.length>0)r("pending"),n("complete");else return Promise.resolve();var l=mt.begin("onTree"),u=s.reduce(function(d,h){try{var y=Ga(h);y&&d.push(y)}catch(g){Aa||g.name==="MissingIcon"&&console.error(g)}return d},[]);return new Promise(function(d,h){Promise.all(u).then(function(y){Ya(y,function(){r("active"),r("complete"),n("pending"),typeof t=="function"&&t(),l(),d()})}).catch(function(y){l(),h(y)})})}function Zi(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:null;Ga(e).then(function(a){a&&Ya([a],t)})}function eo(e){return function(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},r=(t||{}).icon?t:Je(t||{}),n=a.mask;return n&&(n=(n||{}).icon?n:Je(n||{})),e(r,f(f({},a),{},{mask:n}))}}var to=function(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},r=a.transform,n=r===void 0?M:r,i=a.symbol,o=i===void 0?!1:i,s=a.mask,l=s===void 0?null:s,u=a.maskId,d=u===void 0?null:u,h=a.classes,y=h===void 0?[]:h,g=a.attributes,S=g===void 0?{}:g,b=a.styles,A=b===void 0?{}:b;if(t){var m=t.prefix,c=t.iconName,x=t.icon;return _e(f({type:"icon"},t),function(){return q("beforeDOMElementCreation",{iconDefinition:t,params:a}),dt({icons:{main:qe(x),mask:l?qe(l.icon):{found:!1,width:null,height:null,icon:{}}},prefix:m,iconName:c,transform:f(f({},M),n),symbol:o,maskId:d,extra:{attributes:S,styles:A,classes:y}})})}},ao={mixout:function(){return{icon:eo(to)}},hooks:function(){return{mutationObserverCallbacks:function(a){return a.treeCallback=zt,a.nodeCallback=Zi,a}}},provides:function(t){t.i2svg=function(a){var r=a.node,n=r===void 0?w:r,i=a.callback,o=i===void 0?function(){}:i;return zt(n,o)},t.generateSvgReplacementMutation=function(a,r){var n=r.iconName,i=r.prefix,o=r.transform,s=r.symbol,l=r.mask,u=r.maskId,d=r.extra;return new Promise(function(h,y){Promise.all([Qe(n,i),l.iconName?Qe(l.iconName,l.prefix):Promise.resolve({found:!1,width:512,height:512,icon:{}})]).then(function(g){var S=Ae(g,2),b=S[0],A=S[1];h([a,dt({icons:{main:b,mask:A},prefix:i,iconName:n,transform:o,symbol:s,maskId:u,extra:d,watchable:!0})])}).catch(y)})},t.generateAbstractIcon=function(a){var r=a.children,n=a.attributes,i=a.main,o=a.transform,s=a.styles,l=Ie(s);l.length>0&&(n.style=l);var u;return ft(o)&&(u=V("generateAbstractTransformGrouping",{main:i,transform:o,containerWidth:i.width,iconWidth:i.width})),r.push(u||i.icon),{children:r,attributes:n}}}},ro={mixout:function(){return{layer:function(a){var r=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},n=r.classes,i=n===void 0?[]:n;return _e({type:"layer"},function(){q("beforeDOMElementCreation",{assembler:a,params:r});var o=[];return a(function(s){Array.isArray(s)?s.map(function(l){o=o.concat(l.abstract)}):o=o.concat(s.abstract)}),[{tag:"span",attributes:{class:["".concat(v.cssPrefix,"-layers")].concat(L(i)).join(" ")},children:o}]})}}}},no={mixout:function(){return{counter:function(a){var r=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{};r.title;var n=r.classes,i=n===void 0?[]:n,o=r.attributes,s=o===void 0?{}:o,l=r.styles,u=l===void 0?{}:l;return _e({type:"counter",content:a},function(){return q("beforeDOMElementCreation",{content:a,params:r}),Di({content:a.toString(),extra:{attributes:s,styles:u,classes:["".concat(v.cssPrefix,"-layers-counter")].concat(L(i))}})})}}}},io={mixout:function(){return{text:function(a){var r=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},n=r.transform,i=n===void 0?M:n,o=r.classes,s=o===void 0?[]:o,l=r.attributes,u=l===void 0?{}:l,d=r.styles,h=d===void 0?{}:d;return _e({type:"text",content:a},function(){return q("beforeDOMElementCreation",{content:a,params:r}),Tt({content:a,transform:f(f({},M),i),extra:{attributes:u,styles:h,classes:["".concat(v.cssPrefix,"-layers-text")].concat(L(s))}})})}}},provides:function(t){t.generateLayersText=function(a,r){var n=r.transform,i=r.extra,o=null,s=null;if(Qt){var l=parseInt(getComputedStyle(a).fontSize,10),u=a.getBoundingClientRect();o=u.width/l,s=u.height/l}return Promise.resolve([a,Tt({content:a.innerHTML,width:o,height:s,transform:n,extra:i,watchable:!0})])}}},Ka=new RegExp('"',"ug"),Rt=[1105920,1112319],$t=f(f(f(f({},{FontAwesome:{normal:"fas",400:"fas"}}),$r),Vn),Xr),tt=Object.keys($t).reduce(function(e,t){return e[t.toLowerCase()]=$t[t],e},{}),oo=Object.keys(tt).reduce(function(e,t){var a=tt[t];return e[t]=a[900]||L(Object.entries(a))[0][1],e},{});function so(e){var t=e.replace(Ka,"");return Ta(L(t)[0]||"")}function lo(e){var t=e.getPropertyValue("font-feature-settings").includes("ss01"),a=e.getPropertyValue("content"),r=a.replace(Ka,""),n=r.codePointAt(0),i=n>=Rt[0]&&n<=Rt[1],o=r.length===2?r[0]===r[1]:!1;return i||o||t}function fo(e,t){var a=e.replace(/^['"]|['"]$/g,"").toLowerCase(),r=parseInt(t),n=isNaN(r)?"normal":r;return(tt[a]||{})[n]||oo[a]}function Wt(e,t){var a="".concat(Gn).concat(t.replace(":","-"));return new Promise(function(r,n){if(e.getAttribute(a)!==null)return r();var i=ne(e.children),o=i.filter(function(Q){return Q.getAttribute(He)===t})[0],s=H.getComputedStyle(e,t),l=s.getPropertyValue("font-family"),u=l.match(Qn),d=s.getPropertyValue("font-weight"),h=s.getPropertyValue("content");if(o&&!u)return e.removeChild(o),r();if(u&&h!=="none"&&h!==""){var y=s.getPropertyValue("content"),g=fo(l,d),S=so(y),b=u[0].startsWith("FontAwesome"),A=lo(s),m=ct(g,S),c=m;if(b){var x=bi(S);x.iconName&&x.prefix&&(m=x.iconName,g=x.prefix)}if(m&&!A&&(!o||o.getAttribute(it)!==g||o.getAttribute(ot)!==c)){e.setAttribute(a,c),o&&e.removeChild(o);var I=Ji(),F=I.extra;F.attributes[He]=t,Qe(m,g).then(function(Q){var ie=dt(f(f({},I),{},{icons:{main:Q,mask:Wa()},prefix:g,iconName:c,extra:F,watchable:!0})),Fe=w.createElementNS("http://www.w3.org/2000/svg","svg");t==="::before"?e.insertBefore(Fe,e.firstChild):e.appendChild(Fe),Fe.outerHTML=ie.map(function(Qa){return me(Qa)}).join(`
`),e.removeAttribute(a),r()}).catch(n)}else r()}else r()})}function uo(e){return Promise.all([Wt(e,"::before"),Wt(e,"::after")])}function co(e){return e.parentNode!==document.head&&!~Xn.indexOf(e.tagName.toUpperCase())&&!e.getAttribute(He)&&(!e.parentNode||e.parentNode.tagName!=="svg")}var mo=function(t){return!!t&&Sa.some(function(a){return t.includes(a)})},ho=function(t){if(!t)return[];var a=new Set,r=t.split(/,(?![^()]*\))/).map(function(l){return l.trim()});r=r.flatMap(function(l){return l.includes("(")?l:l.split(",").map(function(u){return u.trim()})});var n=pe(r),i;try{for(n.s();!(i=n.n()).done;){var o=i.value;if(mo(o)){var s=Sa.reduce(function(l,u){return l.replace(u,"")},o);s!==""&&s!=="*"&&a.add(s)}}}catch(l){n.e(l)}finally{n.f()}return a};function Ut(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1;if(U){var a;if(t)a=e;else if(v.searchPseudoElementsFullScan)a=e.querySelectorAll("*");else{var r=new Set,n=pe(document.styleSheets),i;try{for(n.s();!(i=n.n()).done;){var o=i.value;try{var s=pe(o.cssRules),l;try{for(s.s();!(l=s.n()).done;){var u=l.value,d=ho(u.selectorText),h=pe(d),y;try{for(h.s();!(y=h.n()).done;){var g=y.value;r.add(g)}}catch(b){h.e(b)}finally{h.f()}}}catch(b){s.e(b)}finally{s.f()}}catch(b){v.searchPseudoElementsWarnings&&console.warn("Font Awesome: cannot parse stylesheet: ".concat(o.href," (").concat(b.message,`)
If it declares any Font Awesome CSS pseudo-elements, they will not be rendered as SVG icons. Add crossorigin="anonymous" to the <link>, enable searchPseudoElementsFullScan for slower but more thorough DOM parsing, or suppress this warning by setting searchPseudoElementsWarnings to false.`))}}}catch(b){n.e(b)}finally{n.f()}if(!r.size)return;var S=Array.from(r).join(", ");try{a=e.querySelectorAll(S)}catch{}}return new Promise(function(b,A){var m=ne(a).filter(co).map(uo),c=mt.begin("searchPseudoElements");Va(),Promise.all(m).then(function(){c(),et(),b()}).catch(function(){c(),et(),A()})})}}var vo={hooks:function(){return{mutationObserverCallbacks:function(a){return a.pseudoElementsCallback=Ut,a}}},provides:function(t){t.pseudoElements2svg=function(a){var r=a.node,n=r===void 0?w:r;v.searchPseudoElements&&Ut(n)}}},Bt=!1,go={mixout:function(){return{dom:{unwatch:function(){Va(),Bt=!0}}}},hooks:function(){return{bootstrap:function(){Dt(Xe("mutationObserverCallbacks",{}))},noAuto:function(){Vi()},watch:function(a){var r=a.observeMutationsRoot;Bt?et():Dt(Xe("mutationObserverCallbacks",{observeMutationsRoot:r}))}}}},Ht=function(t){var a={size:16,x:0,y:0,flipX:!1,flipY:!1,rotate:0};return t.toLowerCase().split(" ").reduce(function(r,n){var i=n.toLowerCase().split("-"),o=i[0],s=i.slice(1).join("-");if(o&&s==="h")return r.flipX=!0,r;if(o&&s==="v")return r.flipY=!0,r;if(s=parseFloat(s),isNaN(s))return r;switch(o){case"grow":r.size=r.size+s;break;case"shrink":r.size=r.size-s;break;case"left":r.x=r.x-s;break;case"right":r.x=r.x+s;break;case"up":r.y=r.y-s;break;case"down":r.y=r.y+s;break;case"rotate":r.rotate=r.rotate+s;break}return r},a)},po={mixout:function(){return{parse:{transform:function(a){return Ht(a)}}}},hooks:function(){return{parseNodeAttributes:function(a,r){var n=r.getAttribute("data-fa-transform");return n&&(a.transform=Ht(n)),a}}},provides:function(t){t.generateAbstractTransformGrouping=function(a){var r=a.main,n=a.transform,i=a.containerWidth,o=a.iconWidth,s={transform:"translate(".concat(i/2," 256)")},l="translate(".concat(n.x*32,", ").concat(n.y*32,") "),u="scale(".concat(n.size/16*(n.flipX?-1:1),", ").concat(n.size/16*(n.flipY?-1:1),") "),d="rotate(".concat(n.rotate," 0 0)"),h={transform:"".concat(l," ").concat(u," ").concat(d)},y={transform:"translate(".concat(o/2*-1," -256)")},g={outer:s,inner:h,path:y};return{tag:"g",attributes:f({},g.outer),children:[{tag:"g",attributes:f({},g.inner),children:[{tag:r.icon.tag,children:r.icon.children,attributes:f(f({},r.icon.attributes),g.path)}]}]}}}},Re={x:0,y:0,width:"100%",height:"100%"};function Yt(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!0;return e.attributes&&(e.attributes.fill||t)&&(e.attributes.fill="black"),e}function yo(e){return e.tag==="g"?e.children:[e]}var bo={hooks:function(){return{parseNodeAttributes:function(a,r){var n=r.getAttribute("data-fa-mask"),i=n?Ce(n.split(" ").map(function(o){return o.trim()})):Wa();return i.prefix||(i.prefix=Y()),a.mask=i,a.maskId=r.getAttribute("data-fa-mask-id"),a}}},provides:function(t){t.generateAbstractMask=function(a){var r=a.children,n=a.attributes,i=a.main,o=a.mask,s=a.maskId,l=a.transform,u=i.width,d=i.icon,h=o.width,y=o.icon,g=fi({transform:l,containerWidth:h,iconWidth:u}),S={tag:"rect",attributes:f(f({},Re),{},{fill:"white"})},b=d.children?{children:d.children.map(Yt)}:{},A={tag:"g",attributes:f({},g.inner),children:[Yt(f({tag:d.tag,attributes:f(f({},d.attributes),g.path)},b))]},m={tag:"g",attributes:f({},g.outer),children:[A]},c="mask-".concat(s||It()),x="clip-".concat(s||It()),I={tag:"mask",attributes:f(f({},Re),{},{id:c,maskUnits:"userSpaceOnUse",maskContentUnits:"userSpaceOnUse"}),children:[S,m]},F={tag:"defs",children:[{tag:"clipPath",attributes:{id:x},children:yo(y)},I]};return r.push(F,{tag:"rect",attributes:f({fill:"currentColor","clip-path":"url(#".concat(x,")"),mask:"url(#".concat(c,")")},Re)}),{children:r,attributes:n}}}},xo={provides:function(t){var a=!1;H.matchMedia&&(a=H.matchMedia("(prefers-reduced-motion: reduce)").matches),t.missingIconAbstract=function(){var r=[],n={fill:"currentColor"},i={attributeType:"XML",repeatCount:"indefinite",dur:"2s"};r.push({tag:"path",attributes:f(f({},n),{},{d:"M156.5,447.7l-12.6,29.5c-18.7-9.5-35.9-21.2-51.5-34.9l22.7-22.7C127.6,430.5,141.5,440,156.5,447.7z M40.6,272H8.5 c1.4,21.2,5.4,41.7,11.7,61.1L50,321.2C45.1,305.5,41.8,289,40.6,272z M40.6,240c1.4-18.8,5.2-37,11.1-54.1l-29.5-12.6 C14.7,194.3,10,216.7,8.5,240H40.6z M64.3,156.5c7.8-14.9,17.2-28.8,28.1-41.5L69.7,92.3c-13.7,15.6-25.5,32.8-34.9,51.5 L64.3,156.5z M397,419.6c-13.9,12-29.4,22.3-46.1,30.4l11.9,29.8c20.7-9.9,39.8-22.6,56.9-37.6L397,419.6z M115,92.4 c13.9-12,29.4-22.3,46.1-30.4l-11.9-29.8c-20.7,9.9-39.8,22.6-56.8,37.6L115,92.4z M447.7,355.5c-7.8,14.9-17.2,28.8-28.1,41.5 l22.7,22.7c13.7-15.6,25.5-32.9,34.9-51.5L447.7,355.5z M471.4,272c-1.4,18.8-5.2,37-11.1,54.1l29.5,12.6 c7.5-21.1,12.2-43.5,13.6-66.8H471.4z M321.2,462c-15.7,5-32.2,8.2-49.2,9.4v32.1c21.2-1.4,41.7-5.4,61.1-11.7L321.2,462z M240,471.4c-18.8-1.4-37-5.2-54.1-11.1l-12.6,29.5c21.1,7.5,43.5,12.2,66.8,13.6V471.4z M462,190.8c5,15.7,8.2,32.2,9.4,49.2h32.1 c-1.4-21.2-5.4-41.7-11.7-61.1L462,190.8z M92.4,397c-12-13.9-22.3-29.4-30.4-46.1l-29.8,11.9c9.9,20.7,22.6,39.8,37.6,56.9 L92.4,397z M272,40.6c18.8,1.4,36.9,5.2,54.1,11.1l12.6-29.5C317.7,14.7,295.3,10,272,8.5V40.6z M190.8,50 c15.7-5,32.2-8.2,49.2-9.4V8.5c-21.2,1.4-41.7,5.4-61.1,11.7L190.8,50z M442.3,92.3L419.6,115c12,13.9,22.3,29.4,30.5,46.1 l29.8-11.9C470,128.5,457.3,109.4,442.3,92.3z M397,92.4l22.7-22.7c-15.6-13.7-32.8-25.5-51.5-34.9l-12.6,29.5 C370.4,72.1,384.4,81.5,397,92.4z"})});var o=f(f({},i),{},{attributeName:"opacity"}),s={tag:"circle",attributes:f(f({},n),{},{cx:"256",cy:"364",r:"28"}),children:[]};return a||s.children.push({tag:"animate",attributes:f(f({},i),{},{attributeName:"r",values:"28;14;28;28;14;28;"})},{tag:"animate",attributes:f(f({},o),{},{values:"1;0;1;1;0;1;"})}),r.push(s),r.push({tag:"path",attributes:f(f({},n),{},{opacity:"1",d:"M263.7,312h-16c-6.6,0-12-5.4-12-12c0-71,77.4-63.9,77.4-107.8c0-20-17.8-40.2-57.4-40.2c-29.1,0-44.3,9.6-59.2,28.7 c-3.9,5-11.1,6-16.2,2.4l-13.1-9.2c-5.6-3.9-6.9-11.8-2.6-17.2c21.2-27.2,46.4-44.7,91.2-44.7c52.3,0,97.4,29.8,97.4,80.2 c0,67.6-77.4,63.5-77.4,107.8C275.7,306.6,270.3,312,263.7,312z"}),children:a?[]:[{tag:"animate",attributes:f(f({},o),{},{values:"1;0;0;0;0;1;"})}]}),a||r.push({tag:"path",attributes:f(f({},n),{},{opacity:"0",d:"M232.5,134.5l7,168c0.3,6.4,5.6,11.5,12,11.5h9c6.4,0,11.7-5.1,12-11.5l7-168c0.3-6.8-5.2-12.5-12-12.5h-23 C237.7,122,232.2,127.7,232.5,134.5z"}),children:[{tag:"animate",attributes:f(f({},o),{},{values:"0;0;1;1;0;0;"})}]}),{tag:"g",attributes:{class:"missing"},children:r}}}},wo={hooks:function(){return{parseNodeAttributes:function(a,r){var n=r.getAttribute("data-fa-symbol"),i=n===null?!1:n===""?!0:n;return a.symbol=i,a}}}},ko=[di,ao,ro,no,io,vo,go,po,bo,xo,wo];_i(ko,{mixoutsTo:T});T.noAuto;T.config;var So=T.library;T.dom;var at=T.parse;T.findIconDefinition;T.toHtml;var Ao=T.icon;T.layer;T.text;T.counter;/*!
 * Font Awesome Free 7.1.0 by @fontawesome - https://fontawesome.com
 * License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License)
 * Copyright 2025 Fonticons, Inc.
 */var Io={prefix:"fas",iconName:"xmark",icon:[384,512,[128473,10005,10006,10060,215,"close","multiply","remove","times"],"f00d","M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"]},Po=Io;function _(e,t,a){return(t=Eo(t))in e?Object.defineProperty(e,t,{value:a,enumerable:!0,configurable:!0,writable:!0}):e[t]=a,e}function Vt(e,t){var a=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(n){return Object.getOwnPropertyDescriptor(e,n).enumerable})),a.push.apply(a,r)}return a}function R(e){for(var t=1;t<arguments.length;t++){var a=arguments[t]!=null?arguments[t]:{};t%2?Vt(Object(a),!0).forEach(function(r){_(e,r,a[r])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(a)):Vt(Object(a)).forEach(function(r){Object.defineProperty(e,r,Object.getOwnPropertyDescriptor(a,r))})}return e}function Co(e,t){if(e==null)return{};var a,r,n=_o(e,t);if(Object.getOwnPropertySymbols){var i=Object.getOwnPropertySymbols(e);for(r=0;r<i.length;r++)a=i[r],t.indexOf(a)===-1&&{}.propertyIsEnumerable.call(e,a)&&(n[a]=e[a])}return n}function _o(e,t){if(e==null)return{};var a={};for(var r in e)if({}.hasOwnProperty.call(e,r)){if(t.indexOf(r)!==-1)continue;a[r]=e[r]}return a}function Fo(e,t){if(typeof e!="object"||!e)return e;var a=e[Symbol.toPrimitive];if(a!==void 0){var r=a.call(e,t);if(typeof r!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}function Eo(e){var t=Fo(e,"string");return typeof t=="symbol"?t:t+""}function Se(e){"@babel/helpers - typeof";return Se=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},Se(e)}function $e(e,t){return Array.isArray(t)&&t.length>0||!Array.isArray(t)&&t?_({},e,t):{}}function Oo(e){var t,a=(t={"fa-spin":e.spin,"fa-pulse":e.pulse,"fa-fw":e.fixedWidth,"fa-border":e.border,"fa-li":e.listItem,"fa-inverse":e.inverse,"fa-flip":e.flip===!0,"fa-flip-horizontal":e.flip==="horizontal"||e.flip==="both","fa-flip-vertical":e.flip==="vertical"||e.flip==="both"},_(_(_(_(_(_(_(_(_(_(t,"fa-".concat(e.size),e.size!==null),"fa-rotate-".concat(e.rotation),e.rotation!==null),"fa-rotate-by",e.rotateBy),"fa-pull-".concat(e.pull),e.pull!==null),"fa-swap-opacity",e.swapOpacity),"fa-bounce",e.bounce),"fa-shake",e.shake),"fa-beat",e.beat),"fa-fade",e.fade),"fa-beat-fade",e.beatFade),_(_(_(_(t,"fa-flash",e.flash),"fa-spin-pulse",e.spinPulse),"fa-spin-reverse",e.spinReverse),"fa-width-auto",e.widthAuto));return Object.keys(a).map(function(r){return a[r]?r:null}).filter(function(r){return r})}var To=typeof globalThis<"u"?globalThis:typeof window<"u"?window:typeof global<"u"?global:typeof self<"u"?self:{},Xa={exports:{}};(function(e){(function(t){var a=function(m,c,x){if(!u(c)||h(c)||y(c)||g(c)||l(c))return c;var I,F=0,Q=0;if(d(c))for(I=[],Q=c.length;F<Q;F++)I.push(a(m,c[F],x));else{I={};for(var ie in c)Object.prototype.hasOwnProperty.call(c,ie)&&(I[m(ie,x)]=a(m,c[ie],x))}return I},r=function(m,c){c=c||{};var x=c.separator||"_",I=c.split||/(?=[A-Z])/;return m.split(I).join(x)},n=function(m){return S(m)?m:(m=m.replace(/[\-_\s]+(.)?/g,function(c,x){return x?x.toUpperCase():""}),m.substr(0,1).toLowerCase()+m.substr(1))},i=function(m){var c=n(m);return c.substr(0,1).toUpperCase()+c.substr(1)},o=function(m,c){return r(m,c).toLowerCase()},s=Object.prototype.toString,l=function(m){return typeof m=="function"},u=function(m){return m===Object(m)},d=function(m){return s.call(m)=="[object Array]"},h=function(m){return s.call(m)=="[object Date]"},y=function(m){return s.call(m)=="[object RegExp]"},g=function(m){return s.call(m)=="[object Boolean]"},S=function(m){return m=m-0,m===m},b=function(m,c){var x=c&&"process"in c?c.process:c;return typeof x!="function"?m:function(I,F){return x(I,m,F)}},A={camelize:n,decamelize:o,pascalize:i,depascalize:o,camelizeKeys:function(m,c){return a(b(n,c),m)},decamelizeKeys:function(m,c){return a(b(o,c),m,c)},pascalizeKeys:function(m,c){return a(b(i,c),m)},depascalizeKeys:function(){return this.decamelizeKeys.apply(this,arguments)}};e.exports?e.exports=A:t.humps=A})(To)})(Xa);var No=Xa.exports,jo=["class","style"];function Lo(e){return e.split(";").map(function(t){return t.trim()}).filter(function(t){return t}).reduce(function(t,a){var r=a.indexOf(":"),n=No.camelize(a.slice(0,r)),i=a.slice(r+1).trim();return t[n]=i,t},{})}function Do(e){return e.split(/\s+/).reduce(function(t,a){return t[a]=!0,t},{})}function Ja(e){var t=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},a=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{};if(typeof e=="string")return e;var r=(e.children||[]).map(function(l){return Ja(l)}),n=Object.keys(e.attributes||{}).reduce(function(l,u){var d=e.attributes[u];switch(u){case"class":l.class=Do(d);break;case"style":l.style=Lo(d);break;default:l.attrs[u]=d}return l},{attrs:{},class:{},style:{}});a.class;var i=a.style,o=i===void 0?{}:i,s=Co(a,jo);return tr(e.tag,R(R(R({},t),{},{class:n.class,style:R(R({},n.style),o)},n.attrs),s),r)}var qa=!1;try{qa=!0}catch{}function Mo(){if(!qa&&console&&typeof console.error=="function"){var e;(e=console).error.apply(e,arguments)}}function Gt(e){if(e&&Se(e)==="object"&&e.prefix&&e.iconName&&e.icon)return e;if(at.icon)return at.icon(e);if(e===null)return null;if(Se(e)==="object"&&e.prefix&&e.iconName)return e;if(Array.isArray(e)&&e.length===2)return{prefix:e[0],iconName:e[1]};if(typeof e=="string")return{prefix:"fas",iconName:e}}var zo=Za({name:"FontAwesomeIcon",props:{border:{type:Boolean,default:!1},fixedWidth:{type:Boolean,default:!1},flip:{type:[Boolean,String],default:!1,validator:function(t){return[!0,!1,"horizontal","vertical","both"].indexOf(t)>-1}},icon:{type:[Object,Array,String],required:!0},mask:{type:[Object,Array,String],default:null},maskId:{type:String,default:null},listItem:{type:Boolean,default:!1},pull:{type:String,default:null,validator:function(t){return["right","left"].indexOf(t)>-1}},pulse:{type:Boolean,default:!1},rotation:{type:[String,Number],default:null,validator:function(t){return[90,180,270].indexOf(Number.parseInt(t,10))>-1}},rotateBy:{type:Boolean,default:!1},swapOpacity:{type:Boolean,default:!1},size:{type:String,default:null,validator:function(t){return["2xs","xs","sm","lg","xl","2xl","1x","2x","3x","4x","5x","6x","7x","8x","9x","10x"].indexOf(t)>-1}},spin:{type:Boolean,default:!1},transform:{type:[String,Object],default:null},symbol:{type:[Boolean,String],default:!1},title:{type:String,default:null},titleId:{type:String,default:null},inverse:{type:Boolean,default:!1},bounce:{type:Boolean,default:!1},shake:{type:Boolean,default:!1},beat:{type:Boolean,default:!1},fade:{type:Boolean,default:!1},beatFade:{type:Boolean,default:!1},flash:{type:Boolean,default:!1},spinPulse:{type:Boolean,default:!1},spinReverse:{type:Boolean,default:!1},widthAuto:{type:Boolean,default:!1}},setup:function(t,a){var r=a.attrs,n=Z(function(){return Gt(t.icon)}),i=Z(function(){return $e("classes",Oo(t))}),o=Z(function(){return $e("transform",typeof t.transform=="string"?at.transform(t.transform):t.transform)}),s=Z(function(){return $e("mask",Gt(t.mask))}),l=Z(function(){var d=R(R(R(R({},i.value),o.value),s.value),{},{symbol:t.symbol,maskId:t.maskId});return d.title=t.title,d.titleId=t.titleId,Ao(n.value,d)});er(l,function(d){if(!d)return Mo("Could not find one or more icon(s)",n.value,s.value)},{immediate:!0});var u=Z(function(){return l.value?Ja(l.value.abstract[0],{},r):null});return function(){return u.value}}});const K="http://lms.msk.dinamika-avia.ru";So.add(Po);const Ro={components:{popup:or,FontAwesomeIcon:zo},props:{idEdit:{type:Number,required:!0},idCategory:{type:Number,required:!0},treeData:Object},data(){return{titleauk:"",aukstructures:[],filterByCategoryAukstructures:[],categories:{},link:"",firstId:"",contentStyleObj:{height:""},activeId:this.firstId,curAuk:this.item,showItems:!0,showSearch:!1,isFavorite:!1,loaded:!1,loading:!1,searchTerm:"",matchingFiles:[],aircraftTitle:"",path:"",aircraft:"",error:"",isLoading:!1,alert:!1,alertType:"",overlay:!1,snackbarText:"",favorites:[],highlighted:[],currentHighlight:0,iframe:null,IframeisLoaded:!1}},mounted(){this.getFavorites(),this.aircrafts||this.$store.dispatch("Course/fetchAircrafts"),this.$store.dispatch("Course/fetchCourse",this.idEdit),this.$store.dispatch("Course/fetchCategory",this.idCategory),this.$store.dispatch("Course/fetchCategories"),this.$store.dispatch("Course/fetchAircrafts"),this.$store.dispatch("Course/fetchAircraft",this.aircraft),G.get(K+"/api/course?course_id="+this.idEdit+"&&category_id="+this.idCategory).then(e=>{this.titleauk=e.data[0].title,this.aukstructures=e.data[0].aukstructures,this.filterByCategoryAukstructures=this.aukstructures.filter(t=>t.categories?t.categories.includes(this.categoryCode.toString().trim()):!0).sort((t,a)=>t.id-a.id),this.path=e.data[0].path,this.aircraft=e.data[0].aircraft_id}).catch(e=>{console.error(e)})},watch:{link(e,t){},activeId(e,t){},getFirstAukId:function(e,t){e&&this.getlink(e)}},computed:{...nr("Course",["course","category","totalCourses","aircrafts","aircraft"]),...rr("Course",["categories","courses"]),idEditComputed(){return this.idEdit},idCategoryComputed(){return this.idCategory},categoryCode(){return this.category?this.category.code:null},getFirstAukId(){const e=this.aukstructures.find(t=>t.type===3);return e&&this.getlink(e.id),e?e.id:null}},methods:{replaceNodeContent(e,t){const a=document.createElement("div");a.innerHTML=t;const r=a.firstChild,n=e.attributes;for(let i=n.length-1;i>=0;i--){const o=n.item(i).nodeName,s=n.item(i).nodeValue,l=JSON.parse('"'+s+'"');r.setAttribute(o,l)}e.parentNode.replaceChild(r,e)},async getlink(e){this.isLoading=!0,this.activeId=e;try{const t=await G.get(K+"/api/getlink/"+e);this.link=t.data}catch(t){console.log(t)}finally{this.isLoading=!1}},loadContent(e,t){this.isLoading=!0,this.getlink(e),setTimeout(()=>{const r=this.$refs.myIframe.contentDocument,n=new DOMParser().parseFromString(r.body.innerHTML,"text/html");try{t.forEach(i=>{const o=n.evaluate(i.originalXpath,n,null,XPathResult.FIRST_ORDERED_NODE_TYPE,null).singleNodeValue;if(o){const s=o.parentNode;s.innerHTML=i.highlightedText}})}catch(i){console.log(i)}r.body.innerHTML=n.documentElement.innerHTML,this.highlightNodes(r)},1e3),this.isLoading=!1},highlightNodes(e){this.highlighted=Array.from(e.querySelectorAll(".highlighted")),this.currentHighlight=0,this.scrollToHighlight(this.highlighted[this.currentHighlight])},scrollToHighlight(e){const t=this.$refs.myIframe,a=t.contentWindow.document,r=t.getBoundingClientRect(),i=e.getBoundingClientRect().top-r.top+a.documentElement.scrollTop;a.documentElement.scrollTop=i},scrollToNext(){this.highlighted.length>0?(this.currentHighlight=(this.currentHighlight+1)%this.highlighted.length,this.scrollToHighlight(this.highlighted[this.currentHighlight])):this.currentHighlight=0},scrollToPrev(){this.highlighted.length>0?(this.currentHighlight=(this.currentHighlight-1+this.highlighted.length)%this.highlighted.length,this.scrollToHighlight(this.highlighted[this.currentHighlight])):this.currentHighlight=0},showthumb(e){document.getElementById(e).style.border="2px doted grey ",document.getElementById(e).style.borderRadius="4px",e!==this.activeId&&(document.getElementById(e).style.background="#D3D3D3"),document.getElementById(e).style.transform="scale(1.03)"},hidethumb(e){document.getElementById(e).style.border="none",e!==this.activeId&&(document.getElementById(e).style.background="none"),document.getElementById(e).style.transform="scale(1.0)"},getfirstauk:function(e){G.get(K+"/api/getfirstauk/"+e).then(t=>{this.firstId=t.data,this.getlink(this.firstId)})},toggleFavorite(){this.isFavorite=!this.isFavorite,this.showItems=!1,this.showSearch=!1,this.isFavorite==!1&&(this.showItems=!0)},toggleList(){this.showItems=!this.showItems,this.isFavorite=!1,this.showSearch=!1},toggleSearch(){this.showSearch=!this.showSearch,this.isFavorite=!1,this.showItems=!1,this.showSearch==!1&&(this.showItems=!0)},addToFavorites(e){const t=this.aukstructures.find(a=>a.id===e)?.title;G.post(K+"/api/favorites/add",{course_id:e,title:t}).then(a=>{this.getFavorites()}).catch(a=>{})},getFavorites(){G.get(K+"/api/favorites/").then(e=>{this.favorites=e.data.favorites})},removeFavorite(e){G.delete(K+`/api/favorites/${e}`).then(()=>{this.getFavorites()})},alertFalse(){this.alert=!1},async search(){const e={query:this.searchTerm,path:this.path,aircraft:this.aircraft};if(this.searchTerm.length<3){this.snackbarText="..не меньше трех символов",this.alertType="error",this.alert=!0;return}G.post(K+"/api/search-files/",e).then(t=>{this.matchingFiles=t.data}).catch(t=>{console.log(t)}).finally(()=>{})}}},$o={class:"text-center",style:{fontSize:"20px"}},Wo={key:0,class:"ml-2 mr-2 search-files__total-results"},Uo={key:1,class:"ml-5 mr-5 mt-1 search-files__no-results"},Bo=["onMouseover","onMouseleave","id","onClick"],Ho={id:"iframe-container",style:{"border-radius":"8px"}},Yo=["src"];function Vo(e,t,a,r,n,i){const o=N("v-progress-linear"),s=N("v-sheet"),l=N("v-col"),u=N("v-icon"),d=N("v-row"),h=N("font-awesome-icon"),y=N("v-text-field"),g=N("v-btn"),S=N("v-btn-group"),b=N("v-divider"),A=N("v-card"),m=N("popup");return O(),D(he,null,[n.isLoading?(O(),Ee(o,{key:0,color:"primary",indeterminate:""})):oe("",!0),B(" "+E(a.idEdit)+"--"+E(a.idCategory)+" ",1),t[4]||(t[4]=z("link",{rel:"stylesheet",href:"https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"},null,-1)),B(" "+E(n.link)+" "+E(n.searchTerm)+"-- "+E(n.isLoading)+" ",1),k(A,{color:"#f5f5f5"},{default:P(()=>[k(d,{dense:"","no-gutters":""},{default:P(()=>[k(l,{cols:"3"},{default:P(()=>[k(s,{class:"my-sheet pa-2 mt-1",color:"#f5f5f5",style:{overflow:"auto","overflow-y":"auto"}},{default:P(()=>[k(s,{class:"mx-auto mt-0 mb-3",elevation:"4",rounded:"lg"},{default:P(()=>[z("div",$o,E(n.titleauk.toUpperCase()),1)]),_:1}),k(d,{"no-gutters":"",align:"center "},{default:P(()=>[k(l,{cols:"1",class:"row-with-line"}),k(l,{cols:"4",class:"d-flex align-center"},{default:P(()=>[k(u,{size:"x-large",class:Oe(["icon-list",{active:n.showItems}]),onClick:i.toggleList},{default:P(()=>[B(E(n.showItems?"mdi-view-list":"mdi-view-list-outline"),1)]),_:1},8,["class","onClick"]),k(u,{size:"x-large",class:Oe(["icon-favorite",{active:n.isFavorite}]),onClick:i.toggleFavorite},{default:P(()=>[B(E(n.isFavorite?"mdi-heart":"mdi-heart-outline"),1)]),_:1},8,["class","onClick"]),k(u,{size:"x-large",class:Oe(["icon-search",{active:n.showSearch}]),onClick:i.toggleSearch},{default:P(()=>[B(E(n.showSearch?"mdi-magnify-minus-outline":"mdi-magnify"),1)]),_:1},8,["class","onClick"])]),_:1}),k(l,{cols:"6",class:"row-with-line"}),k(l,{cols:"1",class:"d-flex align-center"},{default:P(()=>[k(u,{size:"x-large",onClick:t[0]||(t[0]=c=>i.addToFavorites(n.activeId)),icon:"mdi-playlist-star",class:"addToFav"})]),_:1})]),_:1}),n.isFavorite?(O(),Ee(d,{key:0,class:"ml-1 mr-1"},{default:P(()=>[z("ul",null,[(O(!0),D(he,null,Te(n.favorites,c=>(O(),D("li",{key:c.id},[B(E(c.title)+" ",1),k(h,{icon:"times",onClick:x=>i.removeFavorite(c.course_id)},null,8,["onClick"])]))),128))])]),_:1})):oe("",!0),n.showSearch?(O(),Ee(d,{key:1},{default:P(()=>[k(y,{class:"ml-5 mr-5",loading:n.loading,density:"compact",modelValue:n.searchTerm,"onUpdate:modelValue":t[1]||(t[1]=c=>n.searchTerm=c),variant:"outlined",rounded:"","append-inner-icon":"mdi-magnify",label:"Поиск","onClick:appendInner":i.search,onKeyup:ir(i.search,["enter"]),hint:"Введи искомый текст для поиска",clearable:"","single-line":""},null,8,["loading","modelValue","onClick:appendInner","onKeyup"]),z("div",null,[n.matchingFiles.length>0?(O(),D("ul",Wo,[B("Всего найдено: "+E(n.matchingFiles.length)+" ",1),k(S,null,{default:P(()=>[k(g,{onClick:i.scrollToPrev},{default:P(()=>[...t[2]||(t[2]=[z("span",null,"▲",-1)])]),_:1},8,["onClick"]),k(g,{onClick:i.scrollToNext},{default:P(()=>[...t[3]||(t[3]=[z("span",null,"▼",-1)])]),_:1},8,["onClick"])]),_:1}),k(b),(O(!0),D(he,null,Te(n.matchingFiles,c=>(O(),D("li",{key:c.file,style:{"white-space":"nowrap"}},[k(g,{onClick:x=>i.loadContent(c.itemId,c.highlightedNodes),class:"text-truncate",style:{"max-width":"100%",overflow:"hidden","text-overflow":"ellipsis"}},{default:P(()=>[B(E(c.title),1)]),_:2},1032,["onClick"])]))),128))])):(O(),D("p",Uo,"Нет результатов"))])]),_:1})):oe("",!0),n.showItems?(O(!0),D(he,{key:2},Te(n.aukstructures,(c,x)=>(O(),D("div",{key:c.parent_id},[z("div",{class:"mt-1 mx-3",style:vt([c.type!==3?{cursor:"default",opacity:".7",color:"green"}:{cursor:"pointer"},{fontSize:`${-5*c.type+30}px`,paddingLeft:`${(c.type-1)*10}px`,display:"inline-block",wordWrap:"break-word"}])},[x!==0?(O(),D("div",{key:0,onMouseover:I=>c.type===3?i.showthumb(c.id):"",onMouseleave:I=>i.hidethumb(c.id),id:c.id,onClick:I=>c.type===3?i.getlink(c.id):""},E(c.title),41,Bo)):oe("",!0)],4)]))),128)):oe("",!0)]),_:1})]),_:1}),k(l,{cols:"9"},{default:P(()=>[k(s,{rounded:"",elevation:"5",class:"my-sheet pa-2 mt-2 mr-2",style:{"border-radius":"8px",overflow:"auto","overflow-y":"auto"}},{default:P(()=>[z("div",Ho,[z("iframe",{class:"hello px-5",src:n.link,ref:"myIframe",name:"iframe_a",onload:"this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';",style:vt(n.contentStyleObj),width:"100%",scrolling:"auto"},null,12,Yo)])]),_:1})]),_:1})]),_:1})]),_:1}),k(m,{alert:n.alert,alertType:n.alertType,snackbarText:n.snackbarText,overlay:n.alert,alertFalse:i.alertFalse},null,8,["alert","alertType","snackbarText","overlay","alertFalse"])],64)}const Xo=ar(Ro,[["render",Vo]]);export{Xo as default};
