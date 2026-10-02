import{I as mn,u as gn,B as tt,J as xt,_ as vn,g as j,c as jt,i as Y,d as P,b as F,w as O,F as gt,G as W,K as ye,L as hn,H as Nt,j as pn,m as bn,M as yn,a as M,o as C,t as E,n as vt,e as X,h as Mt,y as xn,N as xe,O as wn,P as Sn}from"./app-CCIsaL0h.js";import{A as kn}from"./AppToast-DN9_Vx_5.js";/*!
 * Font Awesome Free 7.3.1 by @fontawesome - https://fontawesome.com
 * License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License)
 * Copyright 2026 Fonticons, Inc.
 */function Bt(t,e){(e==null||e>t.length)&&(e=t.length);for(var a=0,n=Array(e);a<e;a++)n[a]=t[a];return n}function An(t){if(Array.isArray(t))return t}function In(t){if(Array.isArray(t))return Bt(t)}function _n(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}function Fn(t,e){for(var a=0;a<e.length;a++){var n=e[a];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(t,ta(n.key),n)}}function Pn(t,e,a){return e&&Fn(t.prototype,e),Object.defineProperty(t,"prototype",{writable:!1}),t}function wt(t,e){var a=typeof Symbol<"u"&&t[Symbol.iterator]||t["@@iterator"];if(!a){if(Array.isArray(t)||(a=se(t))||e){a&&(t=a);var n=0,r=function(){};return{s:r,n:function(){return n>=t.length?{done:!0}:{done:!1,value:t[n++]}},e:function(l){throw l},f:r}}throw new TypeError(`Invalid attempt to iterate non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}var i,o=!0,s=!1;return{s:function(){a=a.call(t)},n:function(){var l=a.next();return o=l.done,l},e:function(l){s=!0,i=l},f:function(){try{o||a.return==null||a.return()}finally{if(s)throw i}}}}function h(t,e,a){return(e=ta(e))in t?Object.defineProperty(t,e,{value:a,enumerable:!0,configurable:!0,writable:!0}):t[e]=a,t}function zn(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function Cn(t,e){var a=t==null?null:typeof Symbol<"u"&&t[Symbol.iterator]||t["@@iterator"];if(a!=null){var n,r,i,o,s=[],l=!0,u=!1;try{if(i=(a=a.call(t)).next,e===0){if(Object(a)!==a)return;l=!1}else for(;!(l=(n=i.call(a)).done)&&(s.push(n.value),s.length!==e);l=!0);}catch(d){u=!0,r=d}finally{try{if(!l&&a.return!=null&&(o=a.return(),Object(o)!==o))return}finally{if(u)throw r}}return s}}function En(){throw new TypeError(`Invalid attempt to destructure non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function On(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function we(t,e){var a=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);e&&(n=n.filter(function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable})),a.push.apply(a,n)}return a}function f(t){for(var e=1;e<arguments.length;e++){var a=arguments[e]!=null?arguments[e]:{};e%2?we(Object(a),!0).forEach(function(n){h(t,n,a[n])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(a)):we(Object(a)).forEach(function(n){Object.defineProperty(t,n,Object.getOwnPropertyDescriptor(a,n))})}return t}function Pt(t,e){return An(t)||Cn(t,e)||se(t,e)||En()}function D(t){return In(t)||zn(t)||se(t)||On()}function Tn(t,e){if(typeof t!="object"||!t)return t;var a=t[Symbol.toPrimitive];if(a!==void 0){var n=a.call(t,e);if(typeof n!="object")return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}function ta(t){var e=Tn(t,"string");return typeof e=="symbol"?e:e+""}function At(t){"@babel/helpers - typeof";return At=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},At(t)}function se(t,e){if(t){if(typeof t=="string")return Bt(t,e);var a={}.toString.call(t).slice(8,-1);return a==="Object"&&t.constructor&&(a=t.constructor.name),a==="Map"||a==="Set"?Array.from(t):a==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(a)?Bt(t,e):void 0}}var Se=function(){},le={},ea={},aa=null,na={mark:Se,measure:Se};try{typeof window<"u"&&(le=window),typeof document<"u"&&(ea=document),typeof MutationObserver<"u"&&(aa=MutationObserver),typeof performance<"u"&&(na=performance)}catch{}var jn=le.navigator||{},ke=jn.userAgent,Ae=ke===void 0?"":ke,G=le,k=ea,Ie=aa,ht=na;G.document;var B=!!k.documentElement&&!!k.head&&typeof k.addEventListener=="function"&&typeof k.createElement=="function",ra=~Ae.indexOf("MSIE")||~Ae.indexOf("Trident/"),pt,Nn=/fa(k|kd|s|r|l|t|d|dr|dl|dt|b|slr|slpr|wsb|tl|ns|nds|es|gt|jr|jfr|jdr|usb|ufsb|udsb|cr|ss|sr|sl|st|sds|sdr|sdl|sdt|sldr|slpdr|pr|ms|vs)?[\-\ ]/,Mn=/Font ?Awesome ?([567 ]*)(Solid|Regular|Light|Thin|Duotone|Brands|Free|Pro|Sharp Duotone|Sharp|Kit|Notdog Duo|Notdog|Chisel|Etch|Graphite|Thumbprint|Jelly Fill|Jelly Duo|Jelly|Utility|Utility Fill|Utility Duo|Slab Press|Slab|Slab Duo|Slab Press Duo|Pixel|Mosaic|Vellum|Whiteboard)?.*/i,ia={classic:{fa:"solid",fas:"solid","fa-solid":"solid",far:"regular","fa-regular":"regular",fal:"light","fa-light":"light",fat:"thin","fa-thin":"thin",fab:"brands","fa-brands":"brands"},duotone:{fa:"solid",fad:"solid","fa-solid":"solid","fa-duotone":"solid",fadr:"regular","fa-regular":"regular",fadl:"light","fa-light":"light",fadt:"thin","fa-thin":"thin"},sharp:{fa:"solid",fass:"solid","fa-solid":"solid",fasr:"regular","fa-regular":"regular",fasl:"light","fa-light":"light",fast:"thin","fa-thin":"thin"},"sharp-duotone":{fa:"solid",fasds:"solid","fa-solid":"solid",fasdr:"regular","fa-regular":"regular",fasdl:"light","fa-light":"light",fasdt:"thin","fa-thin":"thin"},slab:{"fa-regular":"regular",faslr:"regular"},"slab-press":{"fa-regular":"regular",faslpr:"regular"},"slab-duo":{"fa-regular":"regular",fasldr:"regular"},"slab-press-duo":{"fa-regular":"regular",faslpdr:"regular"},thumbprint:{"fa-light":"light",fatl:"light"},vellum:{"fa-solid":"solid",favs:"solid"},pixel:{"fa-regular":"regular",fapr:"regular"},mosaic:{"fa-solid":"solid",fams:"solid"},whiteboard:{"fa-semibold":"semibold",fawsb:"semibold"},notdog:{"fa-solid":"solid",fans:"solid"},"notdog-duo":{"fa-solid":"solid",fands:"solid"},etch:{"fa-solid":"solid",faes:"solid"},graphite:{"fa-thin":"thin",fagt:"thin"},jelly:{"fa-regular":"regular",fajr:"regular"},"jelly-fill":{"fa-regular":"regular",fajfr:"regular"},"jelly-duo":{"fa-regular":"regular",fajdr:"regular"},chisel:{"fa-regular":"regular",facr:"regular"},utility:{"fa-semibold":"semibold",fausb:"semibold"},"utility-duo":{"fa-semibold":"semibold",faudsb:"semibold"},"utility-fill":{"fa-semibold":"semibold",faufsb:"semibold"}},Ln={GROUP:"duotone-group",PRIMARY:"primary",SECONDARY:"secondary"},oa=["fa-classic","fa-duotone","fa-sharp","fa-sharp-duotone","fa-thumbprint","fa-whiteboard","fa-notdog","fa-notdog-duo","fa-chisel","fa-etch","fa-graphite","fa-jelly","fa-jelly-fill","fa-jelly-duo","fa-slab","fa-slab-press","fa-slab-press-duo","fa-slab-duo","fa-mosaic","fa-pixel","fa-vellum","fa-utility","fa-utility-duo","fa-utility-fill"],T="classic",ct="duotone",sa="sharp",la="sharp-duotone",fa="chisel",ua="etch",ca="graphite",da="jelly",ma="jelly-duo",ga="jelly-fill",va="mosaic",ha="notdog",pa="notdog-duo",ba="pixel",ya="slab",xa="slab-duo",wa="slab-press",Sa="slab-press-duo",ka="thumbprint",Aa="utility",Ia="utility-duo",_a="utility-fill",Fa="vellum",Pa="whiteboard",Dn="Classic",$n="Duotone",Rn="Sharp",Wn="Sharp Duotone",Hn="Chisel",Un="Etch",Bn="Graphite",Yn="Jelly",Xn="Jelly Duo",Vn="Jelly Fill",Gn="Mosaic",Kn="Notdog",qn="Notdog Duo",Jn="Pixel",Qn="Slab",Zn="Slab Duo",tr="Slab Press",er="Slab Press Duo",ar="Thumbprint",nr="Utility",rr="Utility Duo",ir="Utility Fill",or="Vellum",sr="Whiteboard",za=[T,ct,sa,la,fa,ua,ca,da,ma,ga,va,ha,pa,ba,ya,xa,wa,Sa,ka,Aa,Ia,_a,Fa,Pa];pt={},h(h(h(h(h(h(h(h(h(h(pt,T,Dn),ct,$n),sa,Rn),la,Wn),fa,Hn),ua,Un),ca,Bn),da,Yn),ma,Xn),ga,Vn),h(h(h(h(h(h(h(h(h(h(pt,va,Gn),ha,Kn),pa,qn),ba,Jn),ya,Qn),xa,Zn),wa,tr),Sa,er),ka,ar),Aa,nr),h(h(h(h(pt,Ia,rr),_a,ir),Fa,or),Pa,sr);var lr={classic:{900:"fas",400:"far",normal:"far",300:"fal",100:"fat"},duotone:{900:"fad",400:"fadr",300:"fadl",100:"fadt"},sharp:{900:"fass",400:"fasr",300:"fasl",100:"fast"},"sharp-duotone":{900:"fasds",400:"fasdr",300:"fasdl",100:"fasdt"},slab:{400:"faslr"},"slab-press":{400:"faslpr"},"slab-duo":{400:"fasldr"},"slab-press-duo":{400:"faslpdr"},vellum:{900:"favs"},mosaic:{900:"fams"},pixel:{400:"fapr"},whiteboard:{600:"fawsb"},thumbprint:{300:"fatl"},notdog:{900:"fans"},"notdog-duo":{900:"fands"},etch:{900:"faes"},graphite:{100:"fagt"},chisel:{400:"facr"},jelly:{400:"fajr"},"jelly-fill":{400:"fajfr"},"jelly-duo":{400:"fajdr"},utility:{600:"fausb"},"utility-duo":{600:"faudsb"},"utility-fill":{600:"faufsb"}},fr={"Font Awesome 7 Free":{900:"fas",400:"far"},"Font Awesome 7 Pro":{900:"fas",400:"far",normal:"far",300:"fal",100:"fat"},"Font Awesome 7 Brands":{400:"fab",normal:"fab"},"Font Awesome 7 Duotone":{900:"fad",400:"fadr",normal:"fadr",300:"fadl",100:"fadt"},"Font Awesome 7 Sharp":{900:"fass",400:"fasr",normal:"fasr",300:"fasl",100:"fast"},"Font Awesome 7 Sharp Duotone":{900:"fasds",400:"fasdr",normal:"fasdr",300:"fasdl",100:"fasdt"},"Font Awesome 7 Jelly":{400:"fajr",normal:"fajr"},"Font Awesome 7 Jelly Fill":{400:"fajfr",normal:"fajfr"},"Font Awesome 7 Jelly Duo":{400:"fajdr",normal:"fajdr"},"Font Awesome 7 Slab":{400:"faslr",normal:"faslr"},"Font Awesome 7 Slab Press":{400:"faslpr",normal:"faslpr"},"Font Awesome 7 Slab Duo":{400:"fasldr",normal:"fasldr"},"Font Awesome 7 Slab Press Duo":{400:"faslpdr",normal:"faslpdr"},"Font Awesome 7 Pixel":{400:"fapr",normal:"fapr"},"Font Awesome 7 Mosaic":{900:"fams",normal:"fams"},"Font Awesome 7 Vellum":{900:"favs",normal:"favs"},"Font Awesome 7 Thumbprint":{300:"fatl",normal:"fatl"},"Font Awesome 7 Notdog":{900:"fans",normal:"fans"},"Font Awesome 7 Notdog Duo":{900:"fands",normal:"fands"},"Font Awesome 7 Etch":{900:"faes",normal:"faes"},"Font Awesome 7 Graphite":{100:"fagt",normal:"fagt"},"Font Awesome 7 Chisel":{400:"facr",normal:"facr"},"Font Awesome 7 Whiteboard":{600:"fawsb",normal:"fawsb"},"Font Awesome 7 Utility":{600:"fausb",normal:"fausb"},"Font Awesome 7 Utility Duo":{600:"faudsb",normal:"faudsb"},"Font Awesome 7 Utility Fill":{600:"faufsb",normal:"faufsb"}},ur=new Map([["classic",{defaultShortPrefixId:"fas",defaultStyleId:"solid",styleIds:["solid","regular","light","thin","brands"],futureStyleIds:[],defaultFontWeight:900}],["duotone",{defaultShortPrefixId:"fad",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["sharp",{defaultShortPrefixId:"fass",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["sharp-duotone",{defaultShortPrefixId:"fasds",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["chisel",{defaultShortPrefixId:"facr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["etch",{defaultShortPrefixId:"faes",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["graphite",{defaultShortPrefixId:"fagt",defaultStyleId:"thin",styleIds:["thin"],futureStyleIds:[],defaultFontWeight:100}],["jelly",{defaultShortPrefixId:"fajr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["jelly-duo",{defaultShortPrefixId:"fajdr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["jelly-fill",{defaultShortPrefixId:"fajfr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["mosaic",{defaultShortPrefixId:"fams",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["notdog",{defaultShortPrefixId:"fans",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["notdog-duo",{defaultShortPrefixId:"fands",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["pixel",{defaultShortPrefixId:"fapr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab",{defaultShortPrefixId:"faslr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab-duo",{defaultShortPrefixId:"fasldr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab-press",{defaultShortPrefixId:"faslpr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab-press-duo",{defaultShortPrefixId:"faslpdr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["thumbprint",{defaultShortPrefixId:"fatl",defaultStyleId:"light",styleIds:["light"],futureStyleIds:[],defaultFontWeight:300}],["utility",{defaultShortPrefixId:"fausb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["utility-duo",{defaultShortPrefixId:"faudsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["utility-fill",{defaultShortPrefixId:"faufsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["vellum",{defaultShortPrefixId:"favs",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["whiteboard",{defaultShortPrefixId:"fawsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}]]),cr={chisel:{regular:"facr"},classic:{brands:"fab",light:"fal",regular:"far",solid:"fas",thin:"fat"},duotone:{light:"fadl",regular:"fadr",solid:"fad",thin:"fadt"},etch:{solid:"faes"},graphite:{thin:"fagt"},jelly:{regular:"fajr"},"jelly-duo":{regular:"fajdr"},"jelly-fill":{regular:"fajfr"},mosaic:{solid:"fams"},notdog:{solid:"fans"},"notdog-duo":{solid:"fands"},pixel:{regular:"fapr"},sharp:{light:"fasl",regular:"fasr",solid:"fass",thin:"fast"},"sharp-duotone":{light:"fasdl",regular:"fasdr",solid:"fasds",thin:"fasdt"},slab:{regular:"faslr"},"slab-duo":{regular:"fasldr"},"slab-press":{regular:"faslpr"},"slab-press-duo":{regular:"faslpdr"},thumbprint:{light:"fatl"},utility:{semibold:"fausb"},"utility-duo":{semibold:"faudsb"},"utility-fill":{semibold:"faufsb"},vellum:{solid:"favs"},whiteboard:{semibold:"fawsb"}},Ca=["fak","fa-kit","fakd","fa-kit-duotone"],_e={kit:{fak:"kit","fa-kit":"kit"},"kit-duotone":{fakd:"kit-duotone","fa-kit-duotone":"kit-duotone"}},dr=["kit"],mr="kit",gr="kit-duotone",vr="Kit",hr="Kit Duotone";h(h({},mr,vr),gr,hr);var pr={kit:{"fa-kit":"fak"}},br={"Font Awesome Kit":{400:"fak",normal:"fak"},"Font Awesome Kit Duotone":{400:"fakd",normal:"fakd"}},yr={kit:{fak:"fa-kit"}},Fe={kit:{kit:"fak"},"kit-duotone":{"kit-duotone":"fakd"}},bt,yt={GROUP:"duotone-group",SWAP_OPACITY:"swap-opacity",PRIMARY:"primary",SECONDARY:"secondary"},xr=["fa-classic","fa-duotone","fa-sharp","fa-sharp-duotone","fa-thumbprint","fa-whiteboard","fa-notdog","fa-notdog-duo","fa-chisel","fa-etch","fa-graphite","fa-jelly","fa-jelly-fill","fa-jelly-duo","fa-slab","fa-slab-press","fa-slab-press-duo","fa-slab-duo","fa-mosaic","fa-pixel","fa-vellum","fa-utility","fa-utility-duo","fa-utility-fill"],wr="classic",Sr="duotone",kr="sharp",Ar="sharp-duotone",Ir="chisel",_r="etch",Fr="graphite",Pr="jelly",zr="jelly-duo",Cr="jelly-fill",Er="mosaic",Or="notdog",Tr="notdog-duo",jr="pixel",Nr="slab",Mr="slab-duo",Lr="slab-press",Dr="slab-press-duo",$r="thumbprint",Rr="utility",Wr="utility-duo",Hr="utility-fill",Ur="vellum",Br="whiteboard",Yr="Classic",Xr="Duotone",Vr="Sharp",Gr="Sharp Duotone",Kr="Chisel",qr="Etch",Jr="Graphite",Qr="Jelly",Zr="Jelly Duo",ti="Jelly Fill",ei="Mosaic",ai="Notdog",ni="Notdog Duo",ri="Pixel",ii="Slab",oi="Slab Duo",si="Slab Press",li="Slab Press Duo",fi="Thumbprint",ui="Utility",ci="Utility Duo",di="Utility Fill",mi="Vellum",gi="Whiteboard";bt={},h(h(h(h(h(h(h(h(h(h(bt,wr,Yr),Sr,Xr),kr,Vr),Ar,Gr),Ir,Kr),_r,qr),Fr,Jr),Pr,Qr),zr,Zr),Cr,ti),h(h(h(h(h(h(h(h(h(h(bt,Er,ei),Or,ai),Tr,ni),jr,ri),Nr,ii),Mr,oi),Lr,si),Dr,li),$r,fi),Rr,ui),h(h(h(h(bt,Wr,ci),Hr,di),Ur,mi),Br,gi);var vi="kit",hi="kit-duotone",pi="Kit",bi="Kit Duotone";h(h({},vi,pi),hi,bi);var yi={classic:{"fa-brands":"fab","fa-duotone":"fad","fa-light":"fal","fa-regular":"far","fa-solid":"fas","fa-thin":"fat"},duotone:{"fa-regular":"fadr","fa-light":"fadl","fa-thin":"fadt"},sharp:{"fa-solid":"fass","fa-regular":"fasr","fa-light":"fasl","fa-thin":"fast"},"sharp-duotone":{"fa-solid":"fasds","fa-regular":"fasdr","fa-light":"fasdl","fa-thin":"fasdt"},slab:{"fa-regular":"faslr"},"slab-press":{"fa-regular":"faslpr"},"slab-duo":{"fa-regular":"fasldr"},"slab-press-duo":{"fa-regular":"faslpdr"},pixel:{"fa-regular":"fapr"},mosaic:{"fa-solid":"fams"},vellum:{"fa-solid":"favs"},whiteboard:{"fa-semibold":"fawsb"},thumbprint:{"fa-light":"fatl"},notdog:{"fa-solid":"fans"},"notdog-duo":{"fa-solid":"fands"},etch:{"fa-solid":"faes"},graphite:{"fa-thin":"fagt"},jelly:{"fa-regular":"fajr"},"jelly-fill":{"fa-regular":"fajfr"},"jelly-duo":{"fa-regular":"fajdr"},chisel:{"fa-regular":"facr"},utility:{"fa-semibold":"fausb"},"utility-duo":{"fa-semibold":"faudsb"},"utility-fill":{"fa-semibold":"faufsb"}},xi={classic:["fas","far","fal","fat","fad"],duotone:["fadr","fadl","fadt"],sharp:["fass","fasr","fasl","fast"],"sharp-duotone":["fasds","fasdr","fasdl","fasdt"],slab:["faslr"],"slab-press":["faslpr"],"slab-duo":["fasldr"],"slab-press-duo":["faslpdr"],pixel:["fapr"],mosaic:["fams"],vellum:["favs"],whiteboard:["fawsb"],thumbprint:["fatl"],notdog:["fans"],"notdog-duo":["fands"],etch:["faes"],graphite:["fagt"],jelly:["fajr"],"jelly-fill":["fajfr"],"jelly-duo":["fajdr"],chisel:["facr"],utility:["fausb"],"utility-duo":["faudsb"],"utility-fill":["faufsb"]},Yt={classic:{fab:"fa-brands",fad:"fa-duotone",fal:"fa-light",far:"fa-regular",fas:"fa-solid",fat:"fa-thin"},duotone:{fadr:"fa-regular",fadl:"fa-light",fadt:"fa-thin"},sharp:{fass:"fa-solid",fasr:"fa-regular",fasl:"fa-light",fast:"fa-thin"},"sharp-duotone":{fasds:"fa-solid",fasdr:"fa-regular",fasdl:"fa-light",fasdt:"fa-thin"},slab:{faslr:"fa-regular"},"slab-press":{faslpr:"fa-regular"},"slab-duo":{fasldr:"fa-regular"},"slab-press-duo":{faslpdr:"fa-regular"},pixel:{fapr:"fa-regular"},mosaic:{fams:"fa-solid"},vellum:{favs:"fa-solid"},whiteboard:{fawsb:"fa-semibold"},thumbprint:{fatl:"fa-light"},notdog:{fans:"fa-solid"},"notdog-duo":{fands:"fa-solid"},etch:{faes:"fa-solid"},graphite:{fagt:"fa-thin"},jelly:{fajr:"fa-regular"},"jelly-fill":{fajfr:"fa-regular"},"jelly-duo":{fajdr:"fa-regular"},chisel:{facr:"fa-regular"},utility:{fausb:"fa-semibold"},"utility-duo":{faudsb:"fa-semibold"},"utility-fill":{faufsb:"fa-semibold"}},wi=["fa-solid","fa-regular","fa-light","fa-thin","fa-duotone","fa-brands","fa-semibold"],Ea=["fa","fas","far","fal","fat","fad","fadr","fadl","fadt","fab","fass","fasr","fasl","fast","fasds","fasdr","fasdl","fasdt","faslr","faslpr","fasldr","faslpdr","fapr","fams","favs","fawsb","fatl","fans","fands","faes","fagt","fajr","fajfr","fajdr","facr","fausb","faudsb","faufsb"].concat(xr,wi),Si=["solid","regular","light","thin","duotone","brands","semibold"],Oa=[1,2,3,4,5,6,7,8,9,10],ki=Oa.concat([11,12,13,14,15,16,17,18,19,20]),Ai=["aw","fw","pull-left","pull-right"],Ii=[].concat(D(Object.keys(xi)),Si,Ai,["2xs","xs","sm","lg","xl","2xl","beat","beat-fade","border","bounce","buzz","canvas-square","canvas-roomy","fade","flip-360","flip-both","flip-horizontal","flip-vertical","flip","float","inverse","jello","layers","layers-bottom-left","layers-bottom-right","layers-counter","layers-text","layers-top-left","layers-top-right","li","pull-end","pull-start","pulse","rotate-180","rotate-270","rotate-90","rotate-by","shake","spin-pulse","spin-reverse","spin","spin-snap","spin-snap-4","spin-snap-8","stack-1x","stack-2x","stack","swing","ul","wag","width-auto","width-fixed",yt.GROUP,yt.SWAP_OPACITY,yt.PRIMARY,yt.SECONDARY]).concat(Oa.map(function(t){return"".concat(t,"x")})).concat(ki.map(function(t){return"w-".concat(t)})),_i={"Font Awesome 5 Free":{900:"fas",400:"far"},"Font Awesome 5 Pro":{900:"fas",400:"far",normal:"far",300:"fal"},"Font Awesome 5 Brands":{400:"fab",normal:"fab"},"Font Awesome 5 Duotone":{900:"fad"}},H="___FONT_AWESOME___",Xt=16,Ta="fa",ja="svg-inline--fa",Q="data-fa-i2svg",Vt="data-fa-pseudo-element",Fi="data-fa-pseudo-element-pending",fe="data-prefix",ue="data-icon",Pe="fontawesome-i2svg",Pi="async",zi=["HTML","HEAD","STYLE","SCRIPT"],Na=["::before","::after",":before",":after"],Ma=function(){try{return!0}catch{return!1}}();function dt(t){return new Proxy(t,{get:function(a,n){return n in a?a[n]:a[T]}})}var La=f({},ia);La[T]=f(f(f(f({},{"fa-duotone":"duotone"}),ia[T]),_e.kit),_e["kit-duotone"]);var Ci=dt(La),Gt=f({},cr);Gt[T]=f(f(f(f({},{duotone:"fad"}),Gt[T]),Fe.kit),Fe["kit-duotone"]);var ze=dt(Gt),Kt=f({},Yt);Kt[T]=f(f({},Kt[T]),yr.kit);var ce=dt(Kt),qt=f({},yi);qt[T]=f(f({},qt[T]),pr.kit);dt(qt);var Ei=Nn,Da="fa-layers-text",Oi=Mn,Ti=f({},lr);dt(Ti);var ji=["class","data-prefix","data-icon","data-fa-transform","data-fa-mask"],Lt=Ln,Ni=[].concat(D(dr),D(Ii)),lt=G.FontAwesomeConfig||{};function Mi(t){var e=k.querySelector("script["+t+"]");if(e)return e.getAttribute(t)}function Li(t){return t===""?!0:t==="false"?!1:t==="true"?!0:t}if(k&&typeof k.querySelector=="function"){var Di=[["data-family-prefix","familyPrefix"],["data-css-prefix","cssPrefix"],["data-family-default","familyDefault"],["data-style-default","styleDefault"],["data-replacement-class","replacementClass"],["data-auto-replace-svg","autoReplaceSvg"],["data-auto-add-css","autoAddCss"],["data-search-pseudo-elements","searchPseudoElements"],["data-search-pseudo-elements-warnings","searchPseudoElementsWarnings"],["data-search-pseudo-elements-full-scan","searchPseudoElementsFullScan"],["data-observe-mutations","observeMutations"],["data-mutate-approach","mutateApproach"],["data-keep-original-source","keepOriginalSource"],["data-measure-performance","measurePerformance"],["data-show-missing-icons","showMissingIcons"]];Di.forEach(function(t){var e=Pt(t,2),a=e[0],n=e[1],r=Li(Mi(a));r!=null&&(lt[n]=r)})}var $a={styleDefault:"solid",familyDefault:T,cssPrefix:Ta,replacementClass:ja,autoReplaceSvg:!0,autoAddCss:!0,searchPseudoElements:!1,searchPseudoElementsWarnings:!0,searchPseudoElementsFullScan:!1,observeMutations:!0,mutateApproach:"async",keepOriginalSource:!0,measurePerformance:!1,showMissingIcons:!0};lt.familyPrefix&&(lt.cssPrefix=lt.familyPrefix);var rt=f(f({},$a),lt);rt.autoReplaceSvg||(rt.observeMutations=!1);var v={};Object.keys($a).forEach(function(t){Object.defineProperty(v,t,{enumerable:!0,set:function(a){rt[t]=a,ft.forEach(function(n){return n(v)})},get:function(){return rt[t]}})});Object.defineProperty(v,"familyPrefix",{enumerable:!0,set:function(e){rt.cssPrefix=e,ft.forEach(function(a){return a(v)})},get:function(){return rt.cssPrefix}});G.FontAwesomeConfig=v;var ft=[];function $i(t){return ft.push(t),function(){ft.splice(ft.indexOf(t),1)}}var et=Xt,R={size:16,x:0,y:0,rotate:0,flipX:!1,flipY:!1};function Ri(t){if(!(!t||!B)){var e=k.createElement("style");e.setAttribute("type","text/css"),e.innerHTML=t;for(var a=k.head.childNodes,n=null,r=a.length-1;r>-1;r--){var i=a[r],o=(i.tagName||"").toUpperCase();["STYLE","LINK"].indexOf(o)>-1&&(n=i)}return k.head.insertBefore(e,n),t}}var Wi="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";function Ce(){for(var t=12,e="";t-- >0;)e+=Wi[Math.random()*62|0];return e}function it(t){for(var e=[],a=(t||[]).length>>>0;a--;)e[a]=t[a];return e}function de(t){return t.classList?it(t.classList):(t.getAttribute("class")||"").split(" ").filter(function(e){return e})}function Ra(t){return"".concat(t).replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/'/g,"&#39;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}function Hi(t){return Object.keys(t||{}).reduce(function(e,a){return e+"".concat(a,'="').concat(Ra(t[a]),'" ')},"").trim()}function zt(t){return Object.keys(t||{}).reduce(function(e,a){return e+"".concat(a,": ").concat(t[a].trim(),";")},"")}function me(t){return t.size!==R.size||t.x!==R.x||t.y!==R.y||t.rotate!==R.rotate||t.flipX||t.flipY}function Ui(t){var e=t.transform,a=t.containerWidth,n=t.iconWidth,r={transform:"translate(".concat(a/2," 256)")},i="translate(".concat(e.x*32,", ").concat(e.y*32,") "),o="scale(".concat(e.size/16*(e.flipX?-1:1),", ").concat(e.size/16*(e.flipY?-1:1),") "),s="rotate(".concat(e.rotate," 0 0)"),l={transform:"".concat(i," ").concat(o," ").concat(s)},u={transform:"translate(".concat(n/2*-1," -256)")};return{outer:r,inner:l,path:u}}function Bi(t){var e=t.transform,a=t.width,n=a===void 0?Xt:a,r=t.height,i=r===void 0?Xt:r,o="";return ra?o+="translate(".concat(e.x/et-n/2,"em, ").concat(e.y/et-i/2,"em) "):o+="translate(calc(-50% + ".concat(e.x/et,"em), calc(-50% + ").concat(e.y/et,"em)) "),o+="scale(".concat(e.size/et*(e.flipX?-1:1),", ").concat(e.size/et*(e.flipY?-1:1),") "),o+="rotate(".concat(e.rotate,"deg) "),o}var Yi=`:root, :host {
  --fa-font-solid: normal 900 1em/1 'Font Awesome 7 Free';
  --fa-font-regular: normal 400 1em/1 'Font Awesome 7 Free';
  --fa-font-light: normal 300 1em/1 'Font Awesome 7 Pro';
  --fa-font-thin: normal 100 1em/1 'Font Awesome 7 Pro';
  --fa-font-duotone: normal 900 1em/1 'Font Awesome 7 Duotone';
  --fa-font-duotone-regular: normal 400 1em/1 'Font Awesome 7 Duotone';
  --fa-font-duotone-light: normal 300 1em/1 'Font Awesome 7 Duotone';
  --fa-font-duotone-thin: normal 100 1em/1 'Font Awesome 7 Duotone';
  --fa-font-brands: normal 400 1em/1 'Font Awesome 7 Brands';
  --fa-font-sharp-solid: normal 900 1em/1 'Font Awesome 7 Sharp';
  --fa-font-sharp-regular: normal 400 1em/1 'Font Awesome 7 Sharp';
  --fa-font-sharp-light: normal 300 1em/1 'Font Awesome 7 Sharp';
  --fa-font-sharp-thin: normal 100 1em/1 'Font Awesome 7 Sharp';
  --fa-font-sharp-duotone-solid: normal 900 1em/1 'Font Awesome 7 Sharp Duotone';
  --fa-font-sharp-duotone-regular: normal 400 1em/1 'Font Awesome 7 Sharp Duotone';
  --fa-font-sharp-duotone-light: normal 300 1em/1 'Font Awesome 7 Sharp Duotone';
  --fa-font-sharp-duotone-thin: normal 100 1em/1 'Font Awesome 7 Sharp Duotone';
  --fa-font-slab-regular: normal 400 1em/1 'Font Awesome 7 Slab';
  --fa-font-slab-press-regular: normal 400 1em/1 'Font Awesome 7 Slab Press';
  --fa-font-slab-duo-regular: normal 400 1em/1 'Font Awesome 7 Slab Duo';
  --fa-font-slab-press-duo-regular: normal 400 1em/1 'Font Awesome 7 Slab Press Duo';
  --fa-font-pixel-regular: normal 400 1em/1 'Font Awesome 7 Pixel';
  --fa-font-mosaic-solid: normal 900 1em/1 'Font Awesome 7 Mosaic';
  --fa-font-vellum-solid: normal 900 1em/1 'Font Awesome 7 Vellum';
  --fa-font-whiteboard-semibold: normal 600 1em/1 'Font Awesome 7 Whiteboard';
  --fa-font-thumbprint-light: normal 300 1em/1 'Font Awesome 7 Thumbprint';
  --fa-font-notdog-solid: normal 900 1em/1 'Font Awesome 7 Notdog';
  --fa-font-notdog-duo-solid: normal 900 1em/1 'Font Awesome 7 Notdog Duo';
  --fa-font-etch-solid: normal 900 1em/1 'Font Awesome 7 Etch';
  --fa-font-graphite-thin: normal 100 1em/1 'Font Awesome 7 Graphite';
  --fa-font-jelly-regular: normal 400 1em/1 'Font Awesome 7 Jelly';
  --fa-font-jelly-fill-regular: normal 400 1em/1 'Font Awesome 7 Jelly Fill';
  --fa-font-jelly-duo-regular: normal 400 1em/1 'Font Awesome 7 Jelly Duo';
  --fa-font-chisel-regular: normal 400 1em/1 'Font Awesome 7 Chisel';
  --fa-font-utility-semibold: normal 600 1em/1 'Font Awesome 7 Utility';
  --fa-font-utility-duo-semibold: normal 600 1em/1 'Font Awesome 7 Utility Duo';
  --fa-font-utility-fill-semibold: normal 600 1em/1 'Font Awesome 7 Utility Fill';
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

.fa-canvas-square {
  padding-block: 0.125em;
  margin-block-end: -0.125em;
}

.fa-canvas-roomy {
  padding-block: 0.25em;
  padding-inline: 0.125em;
  margin-block-end: -0.25em;
  box-sizing: content-box;
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
  animation-timing-function: var(--fa-animation-timing, ease-in-out);
}

.fa-beat-fade {
  animation-name: fa-beat-fade;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-in-out);
}

.fa-flip {
  animation-name: fa-flip;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1.5s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-in-out);
}

.fa-flip-360 {
  animation-name: fa-flip-360;
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
  animation-duration: var(--fa-animation-duration, 0.75s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-in-out);
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

.fa-spin-snap {
  animation-name: fa-spin-snap;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 3s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, linear);
}

.fa-spin-snap-4 {
  animation-name: fa-spin-snap-4;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 2.4s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, linear);
}

.fa-spin-snap-8 {
  animation-name: fa-spin-snap-8;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 4s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, linear);
}

.fa-buzz {
  animation-name: fa-buzz;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 0.6s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, linear);
}

.fa-wag {
  animation-name: fa-wag;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 0.9s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-out);
  transform-origin: bottom center;
}

.fa-float {
  animation-name: fa-float;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 3s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-in-out);
  will-change: transform;
}

.fa-swing {
  animation-name: fa-swing;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 1.2s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-out);
  transform-origin: top center;
}

.fa-jello {
  animation-name: fa-jello;
  animation-delay: var(--fa-animation-delay, 0s);
  animation-direction: var(--fa-animation-direction, normal);
  animation-duration: var(--fa-animation-duration, 0.9s);
  animation-iteration-count: var(--fa-animation-iteration-count, infinite);
  animation-timing-function: var(--fa-animation-timing, ease-out);
}

@media (prefers-reduced-motion: reduce) {
  .fa-beat,
  .fa-bounce,
  .fa-fade,
  .fa-beat-fade,
  .fa-flip,
  .fa-flip-360,
  .fa-pulse,
  .fa-shake,
  .fa-spin,
  .fa-spin-pulse,
  .fa-buzz,
  .fa-float,
  .fa-jello,
  .fa-spin-snap,
  .fa-spin-snap-4,
  .fa-spin-snap-8,
  .fa-swing,
  .fa-wag {
    animation: none !important;
    transition: none !important;
  }
}
@keyframes fa-beat {
  0% {
    transform: scale(1);
  }
  25% {
    transform: scale(calc(1.25 * var(--fa-beat-scale, 1.25)));
  }
  45% {
    transform: scale(calc(1.22 * var(--fa-beat-scale, 1.22)));
  }
  65% {
    transform: scale(calc(1.25 * var(--fa-beat-scale, 1.25)));
  }
  90% {
    transform: scale(1);
  }
}
@keyframes fa-bounce {
  0% {
    transform: scale(1, 1) translateY(0);
    animation-timing-function: var(--fa-animation-timing);
  }
  14% {
    transform: scale(var(--fa-bounce-start-scale-x, 1.06), var(--fa-bounce-start-scale-y, 0.94)) translateY(var(--fa-bounce-anticipation, 3px));
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 0.33);
  }
  32% {
    transform: scale(var(--fa-bounce-jump-scale-x, 0.94), var(--fa-bounce-jump-scale-y, 1.12)) translateY(calc(-1 * var(--fa-bounce-height, 0.5em)));
    animation-timing-function: cubic-bezier(0.33, 0.66, 0.66, 1);
  }
  52% {
    transform: scale(1, 1) translateY(calc(-1 * var(--fa-bounce-height, 0.5em) * 1.1));
    animation-timing-function: cubic-bezier(0.5, 0, 1, 0.5);
  }
  70% {
    transform: scale(var(--fa-bounce-land-scale-x, 1.06), var(--fa-bounce-land-scale-y, 0.92)) translateY(0);
    animation-timing-function: cubic-bezier(0.33, 0.33, 0.66, 1);
  }
  85% {
    transform: scale(0.98, 1.04) translateY(calc(-2px * var(--fa-bounce-rebound, 1)));
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 1);
  }
  100% {
    transform: scale(1, 1) translateY(0);
  }
}
@keyframes fa-fade {
  0% {
    opacity: 1;
    transform: scale(1);
    animation-timing-function: cubic-bezier(0.2, 0, 0.4, 1);
  }
  40% {
    opacity: var(--fa-fade-opacity, 0.4);
    transform: scale(0.98);
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}
@keyframes fa-beat-fade {
  0% {
    opacity: var(--fa-beat-fade-opacity, 0.4);
    transform: scale(1);
    animation-timing-function: cubic-bezier(0.2, 0, 0.4, 1);
  }
  25% {
    opacity: calc(var(--fa-beat-fade-opacity, 0.4) + 0.4);
    transform: scale(var(--fa-beat-fade-scale, 1.28));
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  45% {
    opacity: 1;
    transform: scale(var(--fa-beat-fade-scale, 1.25));
    animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  }
  65% {
    opacity: calc(var(--fa-beat-fade-opacity, 0.4) + 0.4);
    transform: scale(var(--fa-beat-fade-scale, 1.28));
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  100% {
    opacity: var(--fa-beat-fade-opacity, 0.4);
    transform: scale(1);
  }
}
@keyframes fa-flip {
  0% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), 0deg);
    animation-timing-function: cubic-bezier(0.2, 0, 0.4, 1);
  }
  8% {
    transform: perspective(2em) scale(var(--fa-flip-anticipation-scale, 0.95)) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), 0deg);
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 0.33);
  }
  35% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), calc(var(--fa-flip-angle, -360deg) * 0.6));
    animation-timing-function: linear;
  }
  65% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), calc(var(--fa-flip-angle, -360deg) * 0.5));
    animation-timing-function: cubic-bezier(0.33, 0.66, 0.66, 1);
  }
  92% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), calc(var(--fa-flip-angle, -360deg) * var(--fa-flip-overshoot, 1.04)));
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 1);
  }
  100% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), var(--fa-flip-angle, -360deg));
  }
}
@keyframes fa-flip-360 {
  0% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), 0deg);
    animation-timing-function: cubic-bezier(0.2, 0, 0.4, 1);
  }
  8% {
    transform: perspective(2em) scale(var(--fa-flip-anticipation-scale, 0.95)) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), 0deg);
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 0.33);
  }
  50% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), calc(var(--fa-flip-angle, -360deg) * 0.6));
    animation-timing-function: cubic-bezier(0.33, 0.66, 0.66, 1);
  }
  80% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), calc(var(--fa-flip-angle, -360deg) * var(--fa-flip-overshoot, 1.04)));
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 1);
  }
  100% {
    transform: perspective(2em) scale(1) rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), var(--fa-flip-angle, -360deg));
  }
}
@keyframes fa-shake {
  0% {
    transform: rotate(0deg);
    animation-timing-function: cubic-bezier(0.2, 0, 0.8, 1);
  }
  8% {
    transform: rotate(35deg) translateX(1px);
    animation-timing-function: cubic-bezier(0.3, 0, 0.7, 1);
  }
  20% {
    transform: rotate(-22deg) translateX(-1px);
    animation-timing-function: cubic-bezier(0.3, 0, 0.7, 1);
  }
  35% {
    transform: rotate(15deg) translateX(1px);
    animation-timing-function: cubic-bezier(0.3, 0, 0.7, 1);
  }
  50% {
    transform: rotate(-9deg);
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  65% {
    transform: rotate(5deg);
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  78% {
    transform: rotate(-3deg);
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  90% {
    transform: rotate(1deg);
    animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  }
  100% {
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
@keyframes fa-spin-snap {
  0% {
    transform: rotate(0deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  12% {
    transform: rotate(60deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  16.67% {
    transform: rotate(60deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  28.67% {
    transform: rotate(120deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  33.33% {
    transform: rotate(120deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  45.33% {
    transform: rotate(180deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: rotate(180deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  62% {
    transform: rotate(240deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  66.67% {
    transform: rotate(240deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  78.67% {
    transform: rotate(300deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  83.33% {
    transform: rotate(300deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  95.33% {
    transform: rotate(360deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  100% {
    transform: rotate(360deg);
  }
}
@keyframes fa-spin-snap-4 {
  0% {
    transform: rotate(0deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  15% {
    transform: rotate(90deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  25% {
    transform: rotate(90deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  40% {
    transform: rotate(180deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: rotate(180deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  65% {
    transform: rotate(270deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  75% {
    transform: rotate(270deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  90% {
    transform: rotate(360deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  100% {
    transform: rotate(360deg);
  }
}
@keyframes fa-spin-snap-8 {
  0% {
    transform: rotate(0deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  9% {
    transform: rotate(45deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  12.5% {
    transform: rotate(45deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  21.5% {
    transform: rotate(90deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  25% {
    transform: rotate(90deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  34% {
    transform: rotate(135deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  37.5% {
    transform: rotate(135deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  46.5% {
    transform: rotate(180deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: rotate(180deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  59% {
    transform: rotate(225deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  62.5% {
    transform: rotate(225deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  71.5% {
    transform: rotate(270deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  75% {
    transform: rotate(270deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  84% {
    transform: rotate(315deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  87.5% {
    transform: rotate(315deg);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
  96.5% {
    transform: rotate(360deg);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  100% {
    transform: rotate(360deg);
  }
}
@keyframes fa-buzz {
  0% {
    transform: translateX(0) rotate(0deg);
    animation-timing-function: cubic-bezier(0.1, 0, 0.9, 1);
  }
  5% {
    transform: translateX(var(--fa-buzz-distance, 4px)) rotate(0.5deg);
  }
  10% {
    transform: translateX(calc(-1 * var(--fa-buzz-distance, 4px))) rotate(-0.5deg);
  }
  15% {
    transform: translateX(var(--fa-buzz-distance, 4px)) rotate(0.3deg);
  }
  20% {
    transform: translateX(calc(-1 * var(--fa-buzz-distance, 4px))) rotate(-0.3deg);
  }
  25% {
    transform: translateX(calc(var(--fa-buzz-distance, 4px) * 0.7)) rotate(0.2deg);
  }
  30% {
    transform: translateX(calc(-1 * var(--fa-buzz-distance, 4px) * 0.7)) rotate(-0.2deg);
  }
  35% {
    transform: translateX(calc(var(--fa-buzz-distance, 4px) * 0.4)) rotate(0.1deg);
  }
  40% {
    transform: translateX(0) rotate(0deg);
  }
  100% {
    transform: translateX(0) rotate(0deg);
  }
}
@keyframes fa-wag {
  0% {
    transform: rotate(0deg);
    animation-timing-function: cubic-bezier(0.2, 0, 0.6, 1);
  }
  12% {
    transform: rotate(var(--fa-wag-angle, 12deg));
    animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  }
  24% {
    transform: rotate(2deg);
    animation-timing-function: cubic-bezier(0.2, 0, 0.6, 1);
  }
  36% {
    transform: rotate(calc(var(--fa-wag-angle, 12deg) * 0.85));
    animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  }
  48% {
    transform: rotate(1deg);
    animation-timing-function: cubic-bezier(0.2, 0, 0.6, 1);
  }
  58% {
    transform: rotate(calc(var(--fa-wag-angle, 12deg) * 0.6));
    animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  }
  68% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(0deg);
  }
}
@keyframes fa-float {
  0% {
    transform: translateY(0) translateX(0) rotate(0deg) scale(var(--fa-float-squash-x, 1.02), var(--fa-float-squash-y, 0.98));
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 0.33);
  }
  15% {
    transform: translateY(calc(-0.4 * var(--fa-float-height, 6px))) translateX(var(--fa-float-drift, 1px)) rotate(var(--fa-float-tilt, 1deg)) scale(1, 1);
    animation-timing-function: cubic-bezier(0.33, 0.66, 0.66, 1);
  }
  35% {
    transform: translateY(calc(-1 * var(--fa-float-height, 6px))) translateX(0) rotate(0deg) scale(var(--fa-float-stretch-x, 0.98), var(--fa-float-stretch-y, 1.03));
    animation-timing-function: cubic-bezier(0.5, 0, 0.5, 0);
  }
  50% {
    transform: translateY(calc(-0.92 * var(--fa-float-height, 6px))) translateX(calc(-0.5 * var(--fa-float-drift, 1px))) rotate(calc(-0.5 * var(--fa-float-tilt, 1deg))) scale(0.995, 1.01);
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 0.33);
  }
  70% {
    transform: translateY(calc(-0.3 * var(--fa-float-height, 6px))) translateX(calc(-1 * var(--fa-float-drift, 1px))) rotate(calc(-1 * var(--fa-float-tilt, 1deg))) scale(1, 1);
    animation-timing-function: cubic-bezier(0.33, 0.66, 0.66, 1);
  }
  90% {
    transform: translateY(calc(0.05 * var(--fa-float-height, 6px))) translateX(0) rotate(0deg) scale(var(--fa-float-squash-x, 1.02), var(--fa-float-squash-y, 0.98));
    animation-timing-function: cubic-bezier(0.33, 0, 0.66, 1);
  }
  100% {
    transform: translateY(0) translateX(0) rotate(0deg) scale(var(--fa-float-squash-x, 1.02), var(--fa-float-squash-y, 0.98));
  }
}
@keyframes fa-swing {
  0% {
    transform: rotate(0deg);
    animation-timing-function: cubic-bezier(0.2, 0, 0.8, 1);
  }
  8% {
    transform: rotate(var(--fa-swing-angle, 22deg));
    animation-timing-function: cubic-bezier(0.3, 0, 0.7, 1);
  }
  18% {
    transform: rotate(calc(-1 * var(--fa-swing-angle, 22deg) * 0.85));
    animation-timing-function: cubic-bezier(0.3, 0, 0.7, 1);
  }
  28% {
    transform: rotate(calc(var(--fa-swing-angle, 22deg) * 0.65));
    animation-timing-function: cubic-bezier(0.35, 0, 0.65, 1);
  }
  38% {
    transform: rotate(calc(-1 * var(--fa-swing-angle, 22deg) * 0.45));
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  48% {
    transform: rotate(calc(var(--fa-swing-angle, 22deg) * 0.25));
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  56% {
    transform: rotate(calc(-1 * var(--fa-swing-angle, 22deg) * 0.1));
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  64% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(0deg);
  }
}
@keyframes fa-jello {
  0% {
    transform: scale(1, 1);
    animation-timing-function: cubic-bezier(0.2, 0, 0.8, 1);
  }
  12% {
    transform: scale(var(--fa-jello-scale-x, 1.15), calc(2 - var(--fa-jello-scale-x, 1.15)));
    animation-timing-function: cubic-bezier(0.3, 0, 0.7, 1);
  }
  24% {
    transform: scale(calc(2 - var(--fa-jello-scale-y, 1.12)), var(--fa-jello-scale-y, 1.12));
    animation-timing-function: cubic-bezier(0.3, 0, 0.7, 1);
  }
  36% {
    transform: scale(calc(1 + (var(--fa-jello-scale-x, 1.15) - 1) * 0.5), calc(2 - (1 + (var(--fa-jello-scale-x, 1.15) - 1) * 0.5)));
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  48% {
    transform: scale(calc(2 - (1 + (var(--fa-jello-scale-y, 1.12) - 1) * 0.3)), calc(1 + (var(--fa-jello-scale-y, 1.12) - 1) * 0.3));
    animation-timing-function: cubic-bezier(0.4, 0, 0.6, 1);
  }
  58% {
    transform: scale(1.02, 0.98);
    animation-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  }
  68% {
    transform: scale(1, 1);
  }
  100% {
    transform: scale(1, 1);
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
}`;function Wa(){var t=Ta,e=ja,a=v.cssPrefix,n=v.replacementClass,r=Yi;if(a!==t||n!==e){var i=new RegExp("\\.".concat(t,"\\-"),"g"),o=new RegExp("\\--".concat(t,"\\-"),"g"),s=new RegExp("\\.".concat(e),"g");r=r.replace(i,".".concat(a,"-")).replace(o,"--".concat(a,"-")).replace(s,".".concat(n))}return r}var Ee=!1;function Dt(){v.autoAddCss&&!Ee&&(Ri(Wa()),Ee=!0)}var Xi={mixout:function(){return{dom:{css:Wa,insertCss:Dt}}},hooks:function(){return{beforeDOMElementCreation:function(){Dt()},beforeI2svg:function(){Dt()}}}},U=G||{};U[H]||(U[H]={});U[H].styles||(U[H].styles={});U[H].hooks||(U[H].hooks={});U[H].shims||(U[H].shims=[]);var L=U[H],Ha=[],Ua=function(){k.removeEventListener("DOMContentLoaded",Ua),It=1,Ha.map(function(e){return e()})},It=!1;B&&(It=(k.documentElement.doScroll?/^loaded|^c/:/^loaded|^i|^c/).test(k.readyState),It||k.addEventListener("DOMContentLoaded",Ua));function Vi(t){B&&(It?setTimeout(t,0):Ha.push(t))}function mt(t){var e=t.tag,a=t.attributes,n=a===void 0?{}:a,r=t.children,i=r===void 0?[]:r;return typeof t=="string"?Ra(t):"<".concat(e," ").concat(Hi(n),">").concat(i.map(mt).join(""),"</").concat(e,">")}function Oe(t,e,a){if(t&&t[e]&&t[e][a])return{prefix:e,iconName:a,icon:t[e][a]}}var $t=function(e,a,n,r){var i=Object.keys(e),o=i.length,s=a,l,u,d;for(n===void 0?(l=1,d=e[i[0]]):(l=0,d=n);l<o;l++)u=i[l],d=s(d,e[u],u,e);return d};function Ba(t){return D(t).length!==1?null:t.codePointAt(0).toString(16)}function Te(t){return Object.keys(t).reduce(function(e,a){var n=t[a],r=!!n.icon;return r?e[n.iconName]=n.icon:e[a]=n,e},{})}function Jt(t,e){var a=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{},n=a.skipHooks,r=n===void 0?!1:n,i=Te(e);typeof L.hooks.addPack=="function"&&!r?L.hooks.addPack(t,Te(e)):L.styles[t]=f(f({},L.styles[t]||{}),i),t==="fas"&&Jt("fa",e)}var ut=L.styles,Gi=L.shims,Ya=Object.keys(ce),Ki=Ya.reduce(function(t,e){return t[e]=Object.keys(ce[e]),t},{}),ge=null,Xa={},Va={},Ga={},Ka={},qa={};function qi(t){return~Ni.indexOf(t)}function Ji(t,e){var a=e.split("-"),n=a[0],r=a.slice(1).join("-");return n===t&&r!==""&&!qi(r)?r:null}var Ja=function(){var e=function(i){return $t(ut,function(o,s,l){return o[l]=$t(s,i,{}),o},{})};Xa=e(function(r,i,o){if(i[3]&&(r[i[3]]=o),i[2]){var s=i[2].filter(function(l){return typeof l=="number"});s.forEach(function(l){r[l.toString(16)]=o})}return r}),Va=e(function(r,i,o){if(r[o]=o,i[2]){var s=i[2].filter(function(l){return typeof l=="string"});s.forEach(function(l){r[l]=o})}return r}),qa=e(function(r,i,o){var s=i[2];return r[o]=o,s.forEach(function(l){r[l]=o}),r});var a="far"in ut||v.autoFetchSvg,n=$t(Gi,function(r,i){var o=i[0],s=i[1],l=i[2];return s==="far"&&!a&&(s="fas"),typeof o=="string"&&(r.names[o]={prefix:s,iconName:l}),typeof o=="number"&&(r.unicodes[o.toString(16)]={prefix:s,iconName:l}),r},{names:{},unicodes:{}});Ga=n.names,Ka=n.unicodes,ge=Ct(v.styleDefault,{family:v.familyDefault})};$i(function(t){ge=Ct(t.styleDefault,{family:v.familyDefault})});Ja();function ve(t,e){return(Xa[t]||{})[e]}function Qi(t,e){return(Va[t]||{})[e]}function J(t,e){return(qa[t]||{})[e]}function Qa(t){return Ga[t]||{prefix:null,iconName:null}}function Zi(t){var e=Ka[t],a=ve("fas",t);return e||(a?{prefix:"fas",iconName:a}:null)||{prefix:null,iconName:null}}function K(){return ge}var Za=function(){return{prefix:null,iconName:null,rest:[]}};function to(t){var e=T,a=Ya.reduce(function(n,r){return n[r]="".concat(v.cssPrefix,"-").concat(r),n},{});return za.forEach(function(n){(t.includes(a[n])||t.some(function(r){return Ki[n].includes(r)}))&&(e=n)}),e}function Ct(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},a=e.family,n=a===void 0?T:a,r=Ci[n][t];if(n===ct&&!t)return"fad";var i=ze[n][t]||ze[n][r],o=t in L.styles?t:null,s=i||o||null;return s}function eo(t){var e=[],a=null;return t.forEach(function(n){var r=Ji(v.cssPrefix,n);r?a=r:n&&e.push(n)}),{iconName:a,rest:e}}function je(t){return t.sort().filter(function(e,a,n){return n.indexOf(e)===a})}var Ne=Ea.concat(Ca);function Et(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},a=e.skipLookups,n=a===void 0?!1:a,r=null,i=je(t.filter(function(p){return Ne.includes(p)})),o=je(t.filter(function(p){return!Ne.includes(p)})),s=i.filter(function(p){return r=p,!oa.includes(p)}),l=Pt(s,1),u=l[0],d=u===void 0?null:u,m=to(i),b=f(f({},eo(o)),{},{prefix:Ct(d,{family:m})});return f(f(f({},b),io({values:t,family:m,styles:ut,config:v,canonical:b,givenPrefix:r})),ao(n,r,b))}function ao(t,e,a){var n=a.prefix,r=a.iconName;if(t||!n||!r)return{prefix:n,iconName:r};var i=e==="fa"?Qa(r):{},o=J(n,r);return r=i.iconName||o||r,n=i.prefix||n,n==="far"&&!ut.far&&ut.fas&&!v.autoFetchSvg&&(n="fas"),{prefix:n,iconName:r}}var no=za.filter(function(t){return t!==T||t!==ct}),ro=Object.keys(Yt).filter(function(t){return t!==T}).map(function(t){return Object.keys(Yt[t])}).flat();function io(t){var e=t.values,a=t.family,n=t.canonical,r=t.givenPrefix,i=r===void 0?"":r,o=t.styles,s=o===void 0?{}:o,l=t.config,u=l===void 0?{}:l,d=a===ct,m=e.includes("fa-duotone")||e.includes("fad"),b=u.familyDefault==="duotone",p=n.prefix==="fad"||n.prefix==="fa-duotone";if(!d&&(m||b||p)&&(n.prefix="fad"),(e.includes("fa-brands")||e.includes("fab"))&&(n.prefix="fab"),!n.prefix&&no.includes(a)){var S=Object.keys(s).find(function(_){return ro.includes(_)});if(S||u.autoFetchSvg){var y=ur.get(a).defaultShortPrefixId;n.prefix=y,n.iconName=J(n.prefix,n.iconName)||n.iconName}}return(n.prefix==="fa"||i==="fa")&&(n.prefix=K()||"fas"),n}var oo=function(){function t(){_n(this,t),this.definitions={}}return Pn(t,[{key:"add",value:function(){for(var a=this,n=arguments.length,r=new Array(n),i=0;i<n;i++)r[i]=arguments[i];var o=r.reduce(this._pullDefinitions,{});Object.keys(o).forEach(function(s){a.definitions[s]=f(f({},a.definitions[s]||{}),o[s]),Jt(s,o[s]);var l=ce[T][s];l&&Jt(l,o[s]),Ja()})}},{key:"reset",value:function(){this.definitions={}}},{key:"_pullDefinitions",value:function(a,n){var r=n.prefix&&n.iconName&&n.icon?{0:n}:n;return Object.keys(r).map(function(i){var o=r[i],s=o.prefix,l=o.iconName,u=o.icon,d=u[2];a[s]||(a[s]={}),d.length>0&&d.forEach(function(m){typeof m=="string"&&(a[s][m]=u)}),a[s][l]=u}),a}}])}(),Me=[],at={},nt={},so=Object.keys(nt);function lo(t,e){var a=e.mixoutsTo;return Me=t,at={},Object.keys(nt).forEach(function(n){so.indexOf(n)===-1&&delete nt[n]}),Me.forEach(function(n){var r=n.mixout?n.mixout():{};if(Object.keys(r).forEach(function(o){typeof r[o]=="function"&&(a[o]=r[o]),At(r[o])==="object"&&Object.keys(r[o]).forEach(function(s){a[o]||(a[o]={}),a[o][s]=r[o][s]})}),n.hooks){var i=n.hooks();Object.keys(i).forEach(function(o){at[o]||(at[o]=[]),at[o].push(i[o])})}n.provides&&n.provides(nt)}),a}function Qt(t,e){for(var a=arguments.length,n=new Array(a>2?a-2:0),r=2;r<a;r++)n[r-2]=arguments[r];var i=at[t]||[];return i.forEach(function(o){e=o.apply(null,[e].concat(n))}),e}function Z(t){for(var e=arguments.length,a=new Array(e>1?e-1:0),n=1;n<e;n++)a[n-1]=arguments[n];var r=at[t]||[];r.forEach(function(i){i.apply(null,a)})}function q(){var t=arguments[0],e=Array.prototype.slice.call(arguments,1);return nt[t]?nt[t].apply(null,e):void 0}function Zt(t){t.prefix==="fa"&&(t.prefix="fas");var e=t.iconName,a=t.prefix||K();if(e)return e=J(a,e)||e,Oe(tn.definitions,a,e)||Oe(L.styles,a,e)}var tn=new oo,fo=function(){v.autoReplaceSvg=!1,v.observeMutations=!1,Z("noAuto")},uo={i2svg:function(){var e=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{};return B?(Z("beforeI2svg",e),q("pseudoElements2svg",e),q("i2svg",e)):Promise.reject(new Error("Operation requires a DOM of some kind."))},watch:function(){var e=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{},a=e.autoReplaceSvgRoot;v.autoReplaceSvg===!1&&(v.autoReplaceSvg=!0),v.observeMutations=!0,Vi(function(){mo({autoReplaceSvgRoot:a}),Z("watch",e)})}},co={icon:function(e){if(e===null)return null;if(At(e)==="object"&&e.prefix&&e.iconName)return{prefix:e.prefix,iconName:J(e.prefix,e.iconName)||e.iconName};if(Array.isArray(e)&&e.length===2){var a=e[1].indexOf("fa-")===0?e[1].slice(3):e[1],n=Ct(e[0]);return{prefix:n,iconName:J(n,a)||a}}if(typeof e=="string"&&(e.indexOf("".concat(v.cssPrefix,"-"))>-1||e.match(Ei))){var r=Et(e.split(" "),{skipLookups:!0});return{prefix:r.prefix||K(),iconName:J(r.prefix,r.iconName)||r.iconName}}if(typeof e=="string"){var i=K();return{prefix:i,iconName:J(i,e)||e}}}},N={noAuto:fo,config:v,dom:uo,parse:co,library:tn,findIconDefinition:Zt,toHtml:mt},mo=function(){var e=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{},a=e.autoReplaceSvgRoot,n=a===void 0?k:a;(Object.keys(L.styles).length>0||v.autoFetchSvg)&&B&&v.autoReplaceSvg&&N.dom.i2svg({node:n})};function Ot(t,e){return Object.defineProperty(t,"abstract",{get:e}),Object.defineProperty(t,"html",{get:function(){return t.abstract.map(function(n){return mt(n)})}}),Object.defineProperty(t,"node",{get:function(){if(B){var n=k.createElement("div");return n.innerHTML=t.html,n.children}}}),t}function go(t){var e=t.children,a=t.main,n=t.mask,r=t.attributes,i=t.styles,o=t.transform;if(me(o)&&a.found&&!n.found){var s=a.width,l=a.height,u={x:s/l/2,y:.5};r.style=zt(f(f({},i),{},{"transform-origin":"".concat(u.x+o.x/16,"em ").concat(u.y+o.y/16,"em")}))}return[{tag:"svg",attributes:r,children:e}]}function vo(t){var e=t.prefix,a=t.iconName,n=t.children,r=t.attributes,i=t.symbol,o=i===!0?"".concat(e,"-").concat(v.cssPrefix,"-").concat(a):i;return[{tag:"svg",attributes:{style:"display: none;"},children:[{tag:"symbol",attributes:f(f({},r),{},{id:o}),children:n}]}]}function ho(t){var e=["aria-label","aria-labelledby","title","role"];return e.some(function(a){return a in t})}function he(t){var e=t.icons,a=e.main,n=e.mask,r=t.prefix,i=t.iconName,o=t.transform,s=t.symbol,l=t.maskId,u=t.extra,d=t.watchable,m=d===void 0?!1:d,b=n.found?n:a,p=b.width,S=b.height,y=[v.replacementClass,i?"".concat(v.cssPrefix,"-").concat(i):""].filter(function(z){return u.classes.indexOf(z)===-1}).filter(function(z){return z!==""||!!z}).concat(u.classes).join(" "),_={children:[],attributes:f(f({},u.attributes),{},{"data-prefix":r,"data-icon":i,class:y,role:u.attributes.role||"img",viewBox:"0 0 ".concat(p," ").concat(S)})};!ho(u.attributes)&&!u.attributes["aria-hidden"]&&(_.attributes["aria-hidden"]="true"),m&&(_.attributes[Q]="");var g=f(f({},_),{},{prefix:r,iconName:i,main:a,mask:n,maskId:l,transform:o,symbol:s,styles:f({},u.styles)}),c=n.found&&a.found?q("generateAbstractMask",g)||{children:[],attributes:{}}:q("generateAbstractIcon",g)||{children:[],attributes:{}},x=c.children,A=c.attributes;return g.children=x,g.attributes=A,s?vo(g):go(g)}function Le(t){var e=t.content,a=t.width,n=t.height,r=t.transform,i=t.extra,o=t.watchable,s=o===void 0?!1:o,l=f(f({},i.attributes),{},{class:i.classes.join(" ")});s&&(l[Q]="");var u=f({},i.styles);me(r)&&(u.transform=Bi({transform:r,width:a,height:n}),u["-webkit-transform"]=u.transform);var d=zt(u);d.length>0&&(l.style=d);var m=[];return m.push({tag:"span",attributes:l,children:[e]}),m}function po(t){var e=t.content,a=t.extra,n=f(f({},a.attributes),{},{class:a.classes.join(" ")}),r=zt(a.styles);r.length>0&&(n.style=r);var i=[];return i.push({tag:"span",attributes:n,children:[e]}),i}var Rt=L.styles;function te(t){var e=t[0],a=t[1],n=t.slice(4),r=Pt(n,1),i=r[0],o=null;return Array.isArray(i)?o={tag:"g",attributes:{class:"".concat(v.cssPrefix,"-").concat(Lt.GROUP)},children:[{tag:"path",attributes:{class:"".concat(v.cssPrefix,"-").concat(Lt.SECONDARY),fill:"currentColor",d:i[0]}},{tag:"path",attributes:{class:"".concat(v.cssPrefix,"-").concat(Lt.PRIMARY),fill:"currentColor",d:i[1]}}]}:o={tag:"path",attributes:{fill:"currentColor",d:i}},{found:!0,width:e,height:a,icon:o}}var bo={found:!1,width:512,height:512};function yo(t,e){!Ma&&!v.showMissingIcons&&t&&console.error('Icon with name "'.concat(t,'" and prefix "').concat(e,'" is missing.'))}function ee(t,e){var a=e;return e==="fa"&&v.styleDefault!==null&&(e=K()),new Promise(function(n,r){if(a==="fa"){var i=Qa(t)||{};t=i.iconName||t,e=i.prefix||e}if(t&&e&&Rt[e]&&Rt[e][t]){var o=Rt[e][t];return n(te(o))}yo(t,e),n(f(f({},bo),{},{icon:v.showMissingIcons&&t?q("missingIconAbstract")||{}:{}}))})}var De=function(){},ae=v.measurePerformance&&ht&&ht.mark&&ht.measure?ht:{mark:De,measure:De},st='FA "7.3.1"',xo=function(e){return ae.mark("".concat(st," ").concat(e," begins")),function(){return en(e)}},en=function(e){ae.mark("".concat(st," ").concat(e," ends")),ae.measure("".concat(st," ").concat(e),"".concat(st," ").concat(e," begins"),"".concat(st," ").concat(e," ends"))},pe={begin:xo,end:en},St=function(){};function $e(t){var e=t.getAttribute?t.getAttribute(Q):null;return typeof e=="string"}function wo(t){var e=t.getAttribute?t.getAttribute(fe):null,a=t.getAttribute?t.getAttribute(ue):null;return e&&a}function So(t){return t&&t.classList&&t.classList.contains&&t.classList.contains(v.replacementClass)}function ko(){if(v.autoReplaceSvg===!0)return kt.replace;var t=kt[v.autoReplaceSvg];return t||kt.replace}function Ao(t){return k.createElementNS("http://www.w3.org/2000/svg",t)}function Io(t){return k.createElement(t)}function an(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},a=e.ceFn,n=a===void 0?t.tag==="svg"?Ao:Io:a;if(typeof t=="string")return k.createTextNode(t);var r=n(t.tag);Object.keys(t.attributes||[]).forEach(function(o){r.setAttribute(o,t.attributes[o])});var i=t.children||[];return i.forEach(function(o){r.appendChild(an(o,{ceFn:n}))}),r}function _o(t){var e=" ".concat(t.outerHTML," ");return e="".concat(e,"Font Awesome fontawesome.com "),e}var kt={replace:function(e){var a=e[0];if(a.parentNode)if(e[1].forEach(function(r){a.parentNode.insertBefore(an(r),a)}),a.getAttribute(Q)===null&&v.keepOriginalSource){var n=k.createComment(_o(a));a.parentNode.replaceChild(n,a)}else a.remove()},nest:function(e){var a=e[0],n=e[1];if(~de(a).indexOf(v.replacementClass))return kt.replace(e);var r=new RegExp("".concat(v.cssPrefix,"-.*"));if(delete n[0].attributes.id,n[0].attributes.class){var i=n[0].attributes.class.split(" ").reduce(function(s,l){return l===v.replacementClass||l.match(r)?s.toSvg.push(l):s.toNode.push(l),s},{toNode:[],toSvg:[]});n[0].attributes.class=i.toSvg.join(" "),i.toNode.length===0?a.removeAttribute("class"):a.setAttribute("class",i.toNode.join(" "))}var o=n.map(function(s){return mt(s)}).join(`
`);a.setAttribute(Q,""),a.innerHTML=o}};function Re(t){t()}function nn(t,e){var a=typeof e=="function"?e:St;if(t.length===0)a();else{var n=Re;v.mutateApproach===Pi&&(n=G.requestAnimationFrame||Re),n(function(){var r=ko(),i=pe.begin("mutate");t.map(r),i(),a()})}}var be=!1;function rn(){be=!0}function ne(){be=!1}var _t=null;function We(t){if(Ie&&v.observeMutations){var e=t.treeCallback,a=e===void 0?St:e,n=t.nodeCallback,r=n===void 0?St:n,i=t.pseudoElementsCallback,o=i===void 0?St:i,s=t.observeMutationsRoot,l=s===void 0?k:s;_t=new Ie(function(u){if(!be){var d=K();it(u).forEach(function(m){if(m.type==="childList"&&m.addedNodes.length>0&&!$e(m.addedNodes[0])&&(v.searchPseudoElements&&o(m.target),a(m.target)),m.type==="attributes"&&m.target.parentNode&&v.searchPseudoElements&&o([m.target],!0),m.type==="attributes"&&$e(m.target)&&~ji.indexOf(m.attributeName))if(m.attributeName==="class"&&wo(m.target)){var b=Et(de(m.target)),p=b.prefix,S=b.iconName;m.target.setAttribute(fe,p||d),S&&m.target.setAttribute(ue,S)}else So(m.target)&&r(m.target)})}}),B&&_t.observe(l,{childList:!0,attributes:!0,characterData:!0,subtree:!0})}}function Fo(){_t&&_t.disconnect()}function Po(t){var e=t.getAttribute("style"),a=[];return e&&(a=e.split(";").reduce(function(n,r){var i=r.split(":"),o=i[0],s=i.slice(1);return o&&s.length>0&&(n[o]=s.join(":").trim()),n},{})),a}function zo(t){var e=t.getAttribute("data-prefix"),a=t.getAttribute("data-icon"),n=t.innerText!==void 0?t.innerText.trim():"",r=Et(de(t));return r.prefix||(r.prefix=K()),e&&a&&(r.prefix=e,r.iconName=a),r.iconName&&r.prefix||(r.prefix&&n.length>0&&(r.iconName=Qi(r.prefix,t.innerText)||ve(r.prefix,Ba(t.innerText))),!r.iconName&&v.autoFetchSvg&&t.firstChild&&t.firstChild.nodeType===Node.TEXT_NODE&&(r.iconName=t.firstChild.data)),r}function Co(t){var e=it(t.attributes).reduce(function(a,n){return a.name!=="class"&&a.name!=="style"&&(a[n.name]=n.value),a},{});return e}function Eo(){return{iconName:null,prefix:null,transform:R,symbol:!1,mask:{iconName:null,prefix:null,rest:[]},maskId:null,extra:{classes:[],styles:{},attributes:{}}}}function He(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{styleParser:!0},a=zo(t),n=a.iconName,r=a.prefix,i=a.rest,o=Co(t),s=Qt("parseNodeAttributes",{},t),l=e.styleParser?Po(t):[];return f({iconName:n,prefix:r,transform:R,mask:{iconName:null,prefix:null,rest:[]},maskId:null,symbol:!1,extra:{classes:i,styles:l,attributes:o}},s)}var Oo=L.styles;function on(t){var e=v.autoReplaceSvg==="nest"?He(t,{styleParser:!1}):He(t);return~e.extra.classes.indexOf(Da)?q("generateLayersText",t,e):q("generateSvgReplacementMutation",t,e)}function To(){return[].concat(D(Ca),D(Ea))}function Ue(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:null;if(!B)return Promise.resolve();var a=k.documentElement.classList,n=function(m){return a.add("".concat(Pe,"-").concat(m))},r=function(m){return a.remove("".concat(Pe,"-").concat(m))},i=v.autoFetchSvg?To():oa.concat(Object.keys(Oo));i.includes("fa")||i.push("fa");var o=[".".concat(Da,":not([").concat(Q,"])")].concat(i.map(function(d){return".".concat(d,":not([").concat(Q,"])")})).join(", ");if(o.length===0)return Promise.resolve();var s=[];try{s=it(t.querySelectorAll(o))}catch{}if(s.length>0)n("pending"),r("complete");else return Promise.resolve();var l=pe.begin("onTree"),u=s.reduce(function(d,m){try{var b=on(m);b&&d.push(b)}catch(p){Ma||p.name==="MissingIcon"&&console.error(p)}return d},[]);return new Promise(function(d,m){Promise.all(u).then(function(b){nn(b,function(){n("active"),n("complete"),r("pending"),typeof e=="function"&&e(),l(),d()})}).catch(function(b){l(),m(b)})})}function jo(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:null;on(t).then(function(a){a&&nn([a],e)})}function No(t){return function(e){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},n=(e||{}).icon?e:Zt(e||{}),r=a.mask;return r&&(r=(r||{}).icon?r:Zt(r||{})),t(n,f(f({},a),{},{mask:r}))}}var Mo=function(e){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},n=a.transform,r=n===void 0?R:n,i=a.symbol,o=i===void 0?!1:i,s=a.mask,l=s===void 0?null:s,u=a.maskId,d=u===void 0?null:u,m=a.classes,b=m===void 0?[]:m,p=a.attributes,S=p===void 0?{}:p,y=a.styles,_=y===void 0?{}:y;if(e){var g=e.prefix,c=e.iconName,x=e.icon;return Ot(f({type:"icon"},e),function(){return Z("beforeDOMElementCreation",{iconDefinition:e,params:a}),he({icons:{main:te(x),mask:l?te(l.icon):{found:!1,width:null,height:null,icon:{}}},prefix:g,iconName:c,transform:f(f({},R),r),symbol:o,maskId:d,extra:{attributes:S,styles:_,classes:b}})})}},Lo={mixout:function(){return{icon:No(Mo)}},hooks:function(){return{mutationObserverCallbacks:function(a){return a.treeCallback=Ue,a.nodeCallback=jo,a}}},provides:function(e){e.i2svg=function(a){var n=a.node,r=n===void 0?k:n,i=a.callback,o=i===void 0?function(){}:i;return Ue(r,o)},e.generateSvgReplacementMutation=function(a,n){var r=n.iconName,i=n.prefix,o=n.transform,s=n.symbol,l=n.mask,u=n.maskId,d=n.extra;return new Promise(function(m,b){Promise.all([ee(r,i),l.iconName?ee(l.iconName,l.prefix):Promise.resolve({found:!1,width:512,height:512,icon:{}})]).then(function(p){var S=Pt(p,2),y=S[0],_=S[1];m([a,he({icons:{main:y,mask:_},prefix:i,iconName:r,transform:o,symbol:s,maskId:u,extra:d,watchable:!0})])}).catch(b)})},e.generateAbstractIcon=function(a){var n=a.children,r=a.attributes,i=a.main,o=a.transform,s=a.styles,l=zt(s);l.length>0&&(r.style=l);var u;return me(o)&&(u=q("generateAbstractTransformGrouping",{main:i,transform:o,containerWidth:i.width,iconWidth:i.width})),n.push(u||i.icon),{children:n,attributes:r}}}},Do={mixout:function(){return{layer:function(a){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},r=n.classes,i=r===void 0?[]:r;return Ot({type:"layer"},function(){Z("beforeDOMElementCreation",{assembler:a,params:n});var o=[];return a(function(s){Array.isArray(s)?s.map(function(l){o=o.concat(l.abstract)}):o=o.concat(s.abstract)}),[{tag:"span",attributes:{class:["".concat(v.cssPrefix,"-layers")].concat(D(i)).join(" ")},children:o}]})}}}},$o={mixout:function(){return{counter:function(a){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{};n.title;var r=n.classes,i=r===void 0?[]:r,o=n.attributes,s=o===void 0?{}:o,l=n.styles,u=l===void 0?{}:l;return Ot({type:"counter",content:a},function(){return Z("beforeDOMElementCreation",{content:a,params:n}),po({content:a.toString(),extra:{attributes:s,styles:u,classes:["".concat(v.cssPrefix,"-layers-counter")].concat(D(i))}})})}}}},Ro={mixout:function(){return{text:function(a){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},r=n.transform,i=r===void 0?R:r,o=n.classes,s=o===void 0?[]:o,l=n.attributes,u=l===void 0?{}:l,d=n.styles,m=d===void 0?{}:d;return Ot({type:"text",content:a},function(){return Z("beforeDOMElementCreation",{content:a,params:n}),Le({content:a,transform:f(f({},R),i),extra:{attributes:u,styles:m,classes:["".concat(v.cssPrefix,"-layers-text")].concat(D(s))}})})}}},provides:function(e){e.generateLayersText=function(a,n){var r=n.transform,i=n.extra,o=null,s=null;if(ra){var l=parseInt(getComputedStyle(a).fontSize,10),u=a.getBoundingClientRect();o=u.width/l,s=u.height/l}return Promise.resolve([a,Le({content:a.innerHTML,width:o,height:s,transform:r,extra:i,watchable:!0})])}}},sn=new RegExp('"',"ug"),Be=[1105920,1112319],Ye=f(f(f(f({},{FontAwesome:{normal:"fas",400:"fas"}}),fr),_i),br),re=Object.keys(Ye).reduce(function(t,e){return t[e.toLowerCase()]=Ye[e],t},{}),Wo=Object.keys(re).reduce(function(t,e){var a=re[e];return t[e]=a[900]||D(Object.entries(a))[0][1],t},{});function Ho(t){var e=t.replace(sn,"");return Ba(D(e)[0]||"")}function Uo(t){var e=t.getPropertyValue("font-feature-settings").includes("ss01"),a=t.getPropertyValue("content"),n=a.replace(sn,""),r=n.codePointAt(0),i=r>=Be[0]&&r<=Be[1],o=n.length===2?n[0]===n[1]:!1;return i||o||e}function Bo(t,e){var a=t.replace(/^['"]|['"]$/g,"").toLowerCase(),n=parseInt(e),r=isNaN(n)?"normal":n;return(re[a]||{})[r]||Wo[a]}function Xe(t,e){var a="".concat(Fi).concat(e.replace(":","-"));return new Promise(function(n,r){if(t.getAttribute(a)!==null)return n();var i=it(t.children),o=i.filter(function($){return $.getAttribute(Vt)===e})[0],s=G.getComputedStyle(t,e),l=s.getPropertyValue("font-family"),u=l.match(Oi),d=s.getPropertyValue("font-weight"),m=s.getPropertyValue("content");if(o&&!u)return t.removeChild(o),n();if(u&&m!=="none"&&m!==""){var b=s.getPropertyValue("content"),p=Bo(l,d),S=Ho(b),y=u[0].startsWith("FontAwesome"),_=Uo(s),g=ve(p,S),c=g;if(y){var x=Zi(S);x.iconName&&x.prefix&&(g=x.iconName,p=x.prefix)}if(g&&!_&&(!o||o.getAttribute(fe)!==p||o.getAttribute(ue)!==c)){t.setAttribute(a,c),o&&t.removeChild(o);var A=Eo(),z=A.extra;z.attributes[Vt]=e,ee(g,p).then(function($){var ot=he(f(f({},A),{},{icons:{main:$,mask:Za()},prefix:p,iconName:c,extra:z,watchable:!0})),Tt=k.createElementNS("http://www.w3.org/2000/svg","svg");e==="::before"?t.insertBefore(Tt,t.firstChild):t.appendChild(Tt),Tt.outerHTML=ot.map(function(dn){return mt(dn)}).join(`
`),t.removeAttribute(a),n()}).catch(r)}else n()}else n()})}function Yo(t){return Promise.all([Xe(t,"::before"),Xe(t,"::after")])}function Xo(t){return t.parentNode!==document.head&&!~zi.indexOf(t.tagName.toUpperCase())&&!t.getAttribute(Vt)&&(!t.parentNode||t.parentNode.tagName!=="svg")}var Vo=function(e){return!!e&&Na.some(function(a){return e.includes(a)})},Go=function(e){if(!e)return[];var a=new Set,n=e.split(/,(?![^()]*\))/).map(function(l){return l.trim()});n=n.flatMap(function(l){return l.includes("(")?l:l.split(",").map(function(u){return u.trim()})});var r=wt(n),i;try{for(r.s();!(i=r.n()).done;){var o=i.value;if(Vo(o)){var s=Na.reduce(function(l,u){return l.replace(u,"")},o);s!==""&&s!=="*"&&a.add(s)}}}catch(l){r.e(l)}finally{r.f()}return a};function Ve(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1;if(B){var a;if(e)a=t;else if(v.searchPseudoElementsFullScan)a=t.querySelectorAll("*");else{var n=new Set,r=wt(document.styleSheets),i;try{for(r.s();!(i=r.n()).done;){var o=i.value;try{var s=wt(o.cssRules),l;try{for(s.s();!(l=s.n()).done;){var u=l.value,d=Go(u.selectorText),m=wt(d),b;try{for(m.s();!(b=m.n()).done;){var p=b.value;n.add(p)}}catch(y){m.e(y)}finally{m.f()}}}catch(y){s.e(y)}finally{s.f()}}catch(y){v.searchPseudoElementsWarnings&&console.warn("Font Awesome: cannot parse stylesheet: ".concat(o.href," (").concat(y.message,`)
If it declares any Font Awesome CSS pseudo-elements, they will not be rendered as SVG icons. Add crossorigin="anonymous" to the <link>, enable searchPseudoElementsFullScan for slower but more thorough DOM parsing, or suppress this warning by setting searchPseudoElementsWarnings to false.`))}}}catch(y){r.e(y)}finally{r.f()}if(!n.size)return;var S=Array.from(n).join(", ");try{a=t.querySelectorAll(S)}catch{}}return new Promise(function(y,_){var g=it(a).filter(Xo).map(Yo),c=pe.begin("searchPseudoElements");rn(),Promise.all(g).then(function(){c(),ne(),y()}).catch(function(){c(),ne(),_()})})}}var Ko={hooks:function(){return{mutationObserverCallbacks:function(a){return a.pseudoElementsCallback=Ve,a}}},provides:function(e){e.pseudoElements2svg=function(a){var n=a.node,r=n===void 0?k:n;v.searchPseudoElements&&Ve(r)}}},Ge=!1,qo={mixout:function(){return{dom:{unwatch:function(){rn(),Ge=!0}}}},hooks:function(){return{bootstrap:function(){We(Qt("mutationObserverCallbacks",{}))},noAuto:function(){Fo()},watch:function(a){var n=a.observeMutationsRoot;Ge?ne():We(Qt("mutationObserverCallbacks",{observeMutationsRoot:n}))}}}},Ke=function(e){var a={size:16,x:0,y:0,flipX:!1,flipY:!1,rotate:0};return e.toLowerCase().split(" ").reduce(function(n,r){var i=r.toLowerCase().split("-"),o=i[0],s=i.slice(1).join("-");if(o&&s==="h")return n.flipX=!0,n;if(o&&s==="v")return n.flipY=!0,n;if(s=parseFloat(s),isNaN(s))return n;switch(o){case"grow":n.size=n.size+s;break;case"shrink":n.size=n.size-s;break;case"left":n.x=n.x-s;break;case"right":n.x=n.x+s;break;case"up":n.y=n.y-s;break;case"down":n.y=n.y+s;break;case"rotate":n.rotate=n.rotate+s;break}return n},a)},Jo={mixout:function(){return{parse:{transform:function(a){return Ke(a)}}}},hooks:function(){return{parseNodeAttributes:function(a,n){var r=n.getAttribute("data-fa-transform");return r&&(a.transform=Ke(r)),a}}},provides:function(e){e.generateAbstractTransformGrouping=function(a){var n=a.main,r=a.transform,i=a.containerWidth,o=a.iconWidth,s={transform:"translate(".concat(i/2," 256)")},l="translate(".concat(r.x*32,", ").concat(r.y*32,") "),u="scale(".concat(r.size/16*(r.flipX?-1:1),", ").concat(r.size/16*(r.flipY?-1:1),") "),d="rotate(".concat(r.rotate," 0 0)"),m={transform:"".concat(l," ").concat(u," ").concat(d)},b={transform:"translate(".concat(o/2*-1," -256)")},p={outer:s,inner:m,path:b};return{tag:"g",attributes:f({},p.outer),children:[{tag:"g",attributes:f({},p.inner),children:[{tag:n.icon.tag,children:n.icon.children,attributes:f(f({},n.icon.attributes),p.path)}]}]}}}},Wt={x:0,y:0,width:"100%",height:"100%"};function qe(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!0;return t.attributes&&(t.attributes.fill||e)&&(t.attributes.fill="black"),t}function Qo(t){return t.tag==="g"?t.children:[t]}var Zo={hooks:function(){return{parseNodeAttributes:function(a,n){var r=n.getAttribute("data-fa-mask"),i=r?Et(r.split(" ").map(function(o){return o.trim()})):Za();return i.prefix||(i.prefix=K()),a.mask=i,a.maskId=n.getAttribute("data-fa-mask-id"),a}}},provides:function(e){e.generateAbstractMask=function(a){var n=a.children,r=a.attributes,i=a.main,o=a.mask,s=a.maskId,l=a.transform,u=i.width,d=i.icon,m=o.width,b=o.icon,p=Ui({transform:l,containerWidth:m,iconWidth:u}),S={tag:"rect",attributes:f(f({},Wt),{},{fill:"white"})},y=d.children?{children:d.children.map(qe)}:{},_={tag:"g",attributes:f({},p.inner),children:[qe(f({tag:d.tag,attributes:f(f({},d.attributes),p.path)},y))]},g={tag:"g",attributes:f({},p.outer),children:[_]},c="mask-".concat(s||Ce()),x="clip-".concat(s||Ce()),A={tag:"mask",attributes:f(f({},Wt),{},{id:c,maskUnits:"userSpaceOnUse",maskContentUnits:"userSpaceOnUse"}),children:[S,g]},z={tag:"defs",children:[{tag:"clipPath",attributes:{id:x},children:Qo(b)},A]};return n.push(z,{tag:"rect",attributes:f({fill:"currentColor","clip-path":"url(#".concat(x,")"),mask:"url(#".concat(c,")")},Wt)}),{children:n,attributes:r}}}},ts={provides:function(e){var a=!1;G.matchMedia&&(a=G.matchMedia("(prefers-reduced-motion: reduce)").matches),e.missingIconAbstract=function(){var n=[],r={fill:"currentColor"},i={attributeType:"XML",repeatCount:"indefinite",dur:"2s"};n.push({tag:"path",attributes:f(f({},r),{},{d:"M156.5,447.7l-12.6,29.5c-18.7-9.5-35.9-21.2-51.5-34.9l22.7-22.7C127.6,430.5,141.5,440,156.5,447.7z M40.6,272H8.5 c1.4,21.2,5.4,41.7,11.7,61.1L50,321.2C45.1,305.5,41.8,289,40.6,272z M40.6,240c1.4-18.8,5.2-37,11.1-54.1l-29.5-12.6 C14.7,194.3,10,216.7,8.5,240H40.6z M64.3,156.5c7.8-14.9,17.2-28.8,28.1-41.5L69.7,92.3c-13.7,15.6-25.5,32.8-34.9,51.5 L64.3,156.5z M397,419.6c-13.9,12-29.4,22.3-46.1,30.4l11.9,29.8c20.7-9.9,39.8-22.6,56.9-37.6L397,419.6z M115,92.4 c13.9-12,29.4-22.3,46.1-30.4l-11.9-29.8c-20.7,9.9-39.8,22.6-56.8,37.6L115,92.4z M447.7,355.5c-7.8,14.9-17.2,28.8-28.1,41.5 l22.7,22.7c13.7-15.6,25.5-32.9,34.9-51.5L447.7,355.5z M471.4,272c-1.4,18.8-5.2,37-11.1,54.1l29.5,12.6 c7.5-21.1,12.2-43.5,13.6-66.8H471.4z M321.2,462c-15.7,5-32.2,8.2-49.2,9.4v32.1c21.2-1.4,41.7-5.4,61.1-11.7L321.2,462z M240,471.4c-18.8-1.4-37-5.2-54.1-11.1l-12.6,29.5c21.1,7.5,43.5,12.2,66.8,13.6V471.4z M462,190.8c5,15.7,8.2,32.2,9.4,49.2h32.1 c-1.4-21.2-5.4-41.7-11.7-61.1L462,190.8z M92.4,397c-12-13.9-22.3-29.4-30.4-46.1l-29.8,11.9c9.9,20.7,22.6,39.8,37.6,56.9 L92.4,397z M272,40.6c18.8,1.4,36.9,5.2,54.1,11.1l12.6-29.5C317.7,14.7,295.3,10,272,8.5V40.6z M190.8,50 c15.7-5,32.2-8.2,49.2-9.4V8.5c-21.2,1.4-41.7,5.4-61.1,11.7L190.8,50z M442.3,92.3L419.6,115c12,13.9,22.3,29.4,30.5,46.1 l29.8-11.9C470,128.5,457.3,109.4,442.3,92.3z M397,92.4l22.7-22.7c-15.6-13.7-32.8-25.5-51.5-34.9l-12.6,29.5 C370.4,72.1,384.4,81.5,397,92.4z"})});var o=f(f({},i),{},{attributeName:"opacity"}),s={tag:"circle",attributes:f(f({},r),{},{cx:"256",cy:"364",r:"28"}),children:[]};return a||s.children.push({tag:"animate",attributes:f(f({},i),{},{attributeName:"r",values:"28;14;28;28;14;28;"})},{tag:"animate",attributes:f(f({},o),{},{values:"1;0;1;1;0;1;"})}),n.push(s),n.push({tag:"path",attributes:f(f({},r),{},{opacity:"1",d:"M263.7,312h-16c-6.6,0-12-5.4-12-12c0-71,77.4-63.9,77.4-107.8c0-20-17.8-40.2-57.4-40.2c-29.1,0-44.3,9.6-59.2,28.7 c-3.9,5-11.1,6-16.2,2.4l-13.1-9.2c-5.6-3.9-6.9-11.8-2.6-17.2c21.2-27.2,46.4-44.7,91.2-44.7c52.3,0,97.4,29.8,97.4,80.2 c0,67.6-77.4,63.5-77.4,107.8C275.7,306.6,270.3,312,263.7,312z"}),children:a?[]:[{tag:"animate",attributes:f(f({},o),{},{values:"1;0;0;0;0;1;"})}]}),a||n.push({tag:"path",attributes:f(f({},r),{},{opacity:"0",d:"M232.5,134.5l7,168c0.3,6.4,5.6,11.5,12,11.5h9c6.4,0,11.7-5.1,12-11.5l7-168c0.3-6.8-5.2-12.5-12-12.5h-23 C237.7,122,232.2,127.7,232.5,134.5z"}),children:[{tag:"animate",attributes:f(f({},o),{},{values:"0;0;1;1;0;0;"})}]}),{tag:"g",attributes:{class:"missing"},children:n}}}},es={hooks:function(){return{parseNodeAttributes:function(a,n){var r=n.getAttribute("data-fa-symbol"),i=r===null?!1:r===""?!0:r;return a.symbol=i,a}}}},as=[Xi,Lo,Do,$o,Ro,Ko,qo,Jo,Zo,ts,es];lo(as,{mixoutsTo:N});N.noAuto;N.config;var ns=N.library;N.dom;var ie=N.parse;N.findIconDefinition;N.toHtml;var rs=N.icon;N.layer;N.text;N.counter;/*!
 * Font Awesome Free 7.3.1 by @fontawesome - https://fontawesome.com
 * License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License)
 * Copyright 2026 Fonticons, Inc.
 */var is={prefix:"fas",iconName:"xmark",icon:[384,512,[128473,10005,10006,10060,215,"close","multiply","remove","times"],"f00d","M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"]},os=is;function oe(t,e){(e==null||e>t.length)&&(e=t.length);for(var a=0,n=Array(e);a<e;a++)n[a]=t[a];return n}function ss(t){if(Array.isArray(t))return oe(t)}function w(t,e,a){return(e=ms(e))in t?Object.defineProperty(t,e,{value:a,enumerable:!0,configurable:!0,writable:!0}):t[e]=a,t}function ls(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function fs(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function Je(t,e){var a=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);e&&(n=n.filter(function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable})),a.push.apply(a,n)}return a}function I(t){for(var e=1;e<arguments.length;e++){var a=arguments[e]!=null?arguments[e]:{};e%2?Je(Object(a),!0).forEach(function(n){w(t,n,a[n])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(a)):Je(Object(a)).forEach(function(n){Object.defineProperty(t,n,Object.getOwnPropertyDescriptor(a,n))})}return t}function Ht(t,e){if(t==null)return{};var a,n,r=us(t,e);if(Object.getOwnPropertySymbols){var i=Object.getOwnPropertySymbols(t);for(n=0;n<i.length;n++)a=i[n],e.indexOf(a)===-1&&{}.propertyIsEnumerable.call(t,a)&&(r[a]=t[a])}return r}function us(t,e){if(t==null)return{};var a={};for(var n in t)if({}.hasOwnProperty.call(t,n)){if(e.indexOf(n)!==-1)continue;a[n]=t[n]}return a}function cs(t){return ss(t)||ls(t)||gs(t)||fs()}function ds(t,e){if(typeof t!="object"||!t)return t;var a=t[Symbol.toPrimitive];if(a!==void 0){var n=a.call(t,e);if(typeof n!="object")return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}function ms(t){var e=ds(t,"string");return typeof e=="symbol"?e:e+""}function Ft(t){"@babel/helpers - typeof";return Ft=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},Ft(t)}function gs(t,e){if(t){if(typeof t=="string")return oe(t,e);var a={}.toString.call(t).slice(8,-1);return a==="Object"&&t.constructor&&(a=t.constructor.name),a==="Map"||a==="Set"?Array.from(t):a==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(a)?oe(t,e):void 0}}function Ut(t,e){return Array.isArray(e)&&e.length>0||!Array.isArray(e)&&e?w({},t,e):{}}function vs(t){var e,a=(e={"fa-spin":t.spin,"fa-pulse":t.pulse,"fa-fw":t.fixedWidth,"fa-border":t.border,"fa-li":t.listItem,"fa-inverse":t.inverse,"fa-flip":t.flip===!0,"fa-flip-horizontal":t.flip==="horizontal"||t.flip==="both","fa-flip-vertical":t.flip==="vertical"||t.flip==="both"},w(w(w(w(w(w(w(w(w(w(e,"fa-".concat(t.size),t.size!==null),"fa-rotate-".concat(t.rotation),t.rotation!==null),"fa-rotate-by",t.rotateBy),"fa-pull-".concat(t.pull),t.pull!==null),"fa-swap-opacity",t.swapOpacity),"fa-bounce",t.bounce),"fa-shake",t.shake),"fa-beat",t.beat),"fa-fade",t.fade),"fa-beat-fade",t.beatFade),w(w(w(w(w(w(w(w(w(w(e,"fa-flash",t.flash),"fa-spin-pulse",t.spinPulse),"fa-spin-reverse",t.spinReverse),"fa-width-auto",t.widthAuto),"fa-canvas-square",t.canvasSquare),"fa-canvas-roomy",t.canvasRoomy),"fa-flip-360",t.flip360),"fa-buzz",t.buzz),"fa-float",t.float),"fa-jello",t.jello),w(w(w(w(w(e,"fa-spin-snap",t.spinSnap),"fa-spin-snap-4",t.spinSnap4),"fa-spin-snap-8",t.spinSnap8),"fa-swing",t.swing),"fa-wag",t.wag));return Object.keys(a).map(function(n){return a[n]?n:null}).filter(function(n){return n})}var hs=typeof globalThis<"u"?globalThis:typeof window<"u"?window:typeof global<"u"?global:typeof self<"u"?self:{},ln={exports:{}};(function(t){(function(e){var a=function(g,c,x){if(!u(c)||m(c)||b(c)||p(c)||l(c))return c;var A,z=0,$=0;if(d(c))for(A=[],$=c.length;z<$;z++)A.push(a(g,c[z],x));else{A={};for(var ot in c)Object.prototype.hasOwnProperty.call(c,ot)&&(A[g(ot,x)]=a(g,c[ot],x))}return A},n=function(g,c){c=c||{};var x=c.separator||"_",A=c.split||/(?=[A-Z])/;return g.split(A).join(x)},r=function(g){return S(g)?g:(g=g.replace(/[\-_\s]+(.)?/g,function(c,x){return x?x.toUpperCase():""}),g.substr(0,1).toLowerCase()+g.substr(1))},i=function(g){var c=r(g);return c.substr(0,1).toUpperCase()+c.substr(1)},o=function(g,c){return n(g,c).toLowerCase()},s=Object.prototype.toString,l=function(g){return typeof g=="function"},u=function(g){return g===Object(g)},d=function(g){return s.call(g)=="[object Array]"},m=function(g){return s.call(g)=="[object Date]"},b=function(g){return s.call(g)=="[object RegExp]"},p=function(g){return s.call(g)=="[object Boolean]"},S=function(g){return g=g-0,g===g},y=function(g,c){var x=c&&"process"in c?c.process:c;return typeof x!="function"?g:function(A,z){return x(A,g,z)}},_={camelize:r,decamelize:o,pascalize:i,depascalize:o,camelizeKeys:function(g,c){return a(y(r,c),g)},decamelizeKeys:function(g,c){return a(y(o,c),g,c)},pascalizeKeys:function(g,c){return a(y(i,c),g)},depascalizeKeys:function(){return this.decamelizeKeys.apply(this,arguments)}};t.exports?t.exports=_:e.humps=_})(hs)})(ln);var ps=ln.exports,bs=["gradientFill"],ys=["class","style"],xs=["type","stops","id"];function ws(t){return t.split(";").map(function(e){return e.trim()}).filter(function(e){return e}).reduce(function(e,a){var n=a.indexOf(":"),r=ps.camelize(a.slice(0,n)),i=a.slice(n+1).trim();return e[r]=i,e},{})}function Ss(t){return t.split(/\s+/).reduce(function(e,a){return e[a]=!0,e},{})}function ks(t,e){return xt("stop",I({key:"".concat(e,"-").concat(t.offset),offset:t.offset,"stop-color":t.color},t.opacity!==void 0&&{"stop-opacity":t.opacity}))}function fn(t){if(typeof t=="string")return t;var e=(t.children||[]).map(fn);return t.tag==="path"&&t.attributes&&"fill"in t.attributes?I(I({},t),{},{attributes:I(I({},t.attributes),{},{fill:void 0}),children:e}):I(I({},t),{},{children:e})}function un(t){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},a=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{};if(typeof t=="string")return t;var n=e.gradientFill,r=n===void 0?null:n,i=Ht(e,bs),o=!!r||"fill"in a,s=o?fn(t):t,l=(s.children||[]).map(function(A){return un(A,{},{})}),u=Object.keys(s.attributes||{}).reduce(function(A,z){var $=s.attributes[z];switch(z){case"class":A.class=Ss($);break;case"style":A.style=ws($);break;default:A.attrs[z]=$}return A},{attrs:{},class:{},style:{}});a.class;var d=a.style,m=d===void 0?{}:d,b=Ht(a,ys);if(r&&r.id&&(r.type==="linear"||r.type==="radial")){var p=r.type,S=r.stops,y=S===void 0?[]:S,_=r.id,g=Ht(r,xs),c=p==="linear"?"linearGradient":"radialGradient",x=xt(c,I(I({},g),{},{id:_}),y.map(ks));return xt(s.tag,I(I(I(I({},i),{},{class:u.class,style:I(I({},u.style),m)},u.attrs),b),{},{fill:"url(#".concat(_,")")}),[x].concat(cs(l)))}return xt(t.tag,I(I(I({},i),{},{class:u.class,style:I(I({},u.style),m)},u.attrs),b),l)}var cn=!1;try{cn=!0}catch{}function Qe(){if(!cn&&console&&typeof console.error=="function"){var t;(t=console).error.apply(t,arguments)}}function Ze(t){if(t&&Ft(t)==="object"&&t.prefix&&t.iconName&&t.icon)return t;if(ie.icon)return ie.icon(t);if(t===null)return null;if(Ft(t)==="object"&&t.prefix&&t.iconName)return t;if(Array.isArray(t)&&t.length===2)return{prefix:t[0],iconName:t[1]};if(typeof t=="string")return{prefix:"fas",iconName:t}}var As=mn({name:"FontAwesomeIcon",props:{border:{type:Boolean,default:!1},fixedWidth:{type:Boolean,default:!1},flip:{type:[Boolean,String],default:!1,validator:function(e){return[!0,!1,"horizontal","vertical","both"].indexOf(e)>-1}},icon:{type:[Object,Array,String],required:!0},mask:{type:[Object,Array,String],default:null},maskId:{type:String,default:null},listItem:{type:Boolean,default:!1},pull:{type:String,default:null,validator:function(e){return["right","left"].indexOf(e)>-1}},pulse:{type:Boolean,default:!1},rotation:{type:[String,Number],default:null,validator:function(e){return[90,180,270].indexOf(Number.parseInt(e,10))>-1}},rotateBy:{type:Boolean,default:!1},swapOpacity:{type:Boolean,default:!1},size:{type:String,default:null,validator:function(e){return["2xs","xs","sm","lg","xl","2xl","1x","2x","3x","4x","5x","6x","7x","8x","9x","10x"].indexOf(e)>-1}},spin:{type:Boolean,default:!1},transform:{type:[String,Object],default:null},symbol:{type:[Boolean,String],default:!1},title:{type:String,default:null},titleId:{type:String,default:null},inverse:{type:Boolean,default:!1},bounce:{type:Boolean,default:!1},shake:{type:Boolean,default:!1},beat:{type:Boolean,default:!1},fade:{type:Boolean,default:!1},beatFade:{type:Boolean,default:!1},flash:{type:Boolean,default:!1},spinPulse:{type:Boolean,default:!1},spinReverse:{type:Boolean,default:!1},widthAuto:{type:Boolean,default:!1},canvasSquare:{type:Boolean,default:!1},canvasRoomy:{type:Boolean,default:!1},gradientFill:{type:Object,default:null,validator:function(e){return typeof e.id!="string"||!e.id?(console.warn("FontAwesomeIcon: gradientFill.id must be a non-empty string"),!1):e.type!=="linear"&&e.type!=="radial"?(console.warn('FontAwesomeIcon: gradientFill.type must be "linear" or "radial"'),!1):!0}},flip360:{type:Boolean,default:!1},buzz:{type:Boolean,default:!1},float:{type:Boolean,default:!1},jello:{type:Boolean,default:!1},spinSnap:{type:Boolean,default:!1},spinSnap4:{type:Boolean,default:!1},spinSnap8:{type:Boolean,default:!1},swing:{type:Boolean,default:!1},wag:{type:Boolean,default:!1}},setup:function(e,a){var n=a.attrs,r=tt(function(){return Ze(e.icon)}),i=tt(function(){return Ut("classes",vs(e))}),o=tt(function(){return Ut("transform",typeof e.transform=="string"?ie.transform(e.transform):e.transform)}),s=tt(function(){return Ut("mask",Ze(e.mask))}),l=tt(function(){var d=I(I(I(I({},i.value),o.value),s.value),{},{symbol:e.symbol,maskId:e.maskId});return d.title=e.title,d.titleId=e.titleId,rs(r.value,d)});gn(l,function(d){if(!d)return Qe("Could not find one or more icon(s)",r.value,s.value)},{immediate:!0}),e.gradientFill&&e.symbol&&Qe("gradientFill is not supported when symbol is true and will be ignored");var u=tt(function(){return l.value?un(l.value.abstract[0],{gradientFill:e.symbol?null:e.gradientFill},n):null});return function(){return u.value}}});const V="http://127.0.0.1:8080";ns.add(os);const Is={components:{AppToast:kn,FontAwesomeIcon:As},props:{idEdit:{type:Number,required:!0},idCategory:{type:Number,required:!0},treeData:Object},data(){return{titleauk:"",aukstructures:[],visitedIds:[],activeTitle:"",canScrollUp:!1,filterByCategoryAukstructures:[],categories:{},link:"",contentHtml:"",firstId:"",frameHeight:"320px",activeId:this.firstId,curAuk:this.item,showItems:!0,showSearch:!1,isFavorite:!1,loaded:!1,loading:!1,searchTerm:"",matchingFiles:[],aircraftTitle:"",path:"",aircraft:"",error:"",isLoading:!1,alert:!1,alertType:"",overlay:!1,snackbarText:"",favorites:[],highlighted:[],currentHighlight:0,iframe:null,IframeisLoaded:!1}},mounted(){this.getFavorites(),this.restoreVisited(),this.$nextTick(this.bindScroll),this.aircrafts||this.$store.dispatch("Course/fetchAircrafts"),this.$store.dispatch("Course/fetchCourse",{course_id:this.idEdit,category_id:this.idCategory}),this.$store.dispatch("Course/fetchCategory",this.idCategory),this.$store.dispatch("Course/fetchCategories"),this.$store.dispatch("Course/fetchAircrafts"),this.$store.dispatch("Course/fetchAircraft",this.aircraft),W.get(V+"/api/course",{params:yn({course_id:this.idEdit,category_id:this.idCategory})}).then(t=>{const e=ye(t)[0]||{};this.titleauk=e.title||"",this.aukstructures=e.aukstructures||[];const a=this.categoryCode?String(this.categoryCode).trim():"";this.filterByCategoryAukstructures=a?this.aukstructures.filter(n=>n.categories?n.categories.includes(a):!0).sort((n,r)=>n.id-r.id):this.aukstructures,this.path=e.path,this.aircraft=e.aircraft_id}).catch(t=>{console.error(t)})},beforeUnmount(){var t;if(this.scrollHandler){const e=((t=this.$refs.contentEl)==null?void 0:t.$el)??this.$refs.contentEl;e==null||e.removeEventListener("scroll",this.scrollHandler),this.scrollHandler=null}},watch:{link(t,e){},activeId(t,e){},getFirstAukId:function(t,e){t&&this.getlink(t)}},computed:{...bn("Course",["course","category","totalCourses","aircrafts","aircraft"]),...pn("Course",["categories","courses"]),idEditComputed(){return this.idEdit},idCategoryComputed(){return this.idCategory},categoryCode(){return this.category?this.category.code:null},getFirstAukId(){const t=this.aukstructures.find(e=>e.type===3);return t?t.id:null}},methods:{nodeStyle(t){return{paddingLeft:`${Math.max(0,(t.type-1)*12+8)}px`,fontSize:`${30-t.type*4}px`}},isVisited(t){return this.visitedIds.includes(t)},openModule(t){this.getlink(t.id)},markVisited(t){this.visitedIds.includes(t)||(this.visitedIds=[...this.visitedIds,t]),this.persistVisited()},visitedKey(){return`course-manifest-visited:${this.idEdit}`},persistVisited(){try{window.localStorage.setItem(this.visitedKey(),JSON.stringify(this.visitedIds))}catch{}},restoreVisited(){try{const t=window.localStorage.getItem(this.visitedKey()),e=t?JSON.parse(t):[];this.visitedIds=Array.isArray(e)?e:[]}catch{this.visitedIds=[]}},bindScroll(){var a;const t=((a=this.$refs.contentEl)==null?void 0:a.$el)??this.$refs.contentEl;if(!t)return;let e=null;this.scrollHandler=()=>{e===null&&(e=window.requestAnimationFrame(()=>{e=null,this.canScrollUp=t.scrollTop>240}))},t.addEventListener("scroll",this.scrollHandler,{passive:!0}),this.canScrollUp=t.scrollTop>240},onFrameLoad(t){var e,a;try{const n=(e=t==null?void 0:t.target)==null?void 0:e.contentDocument,r=((a=n==null?void 0:n.body)==null?void 0:a.scrollHeight)??0;r>0&&(this.frameHeight=`${r+20}px`)}catch{}},scrollToTop(){var e;const t=((e=this.$refs.contentEl)==null?void 0:e.$el)??this.$refs.contentEl;t==null||t.scrollTo({top:0,behavior:"smooth"})},replaceNodeContent(t,e){const a=document.createElement("div");a.innerHTML=e;const n=a.firstChild,r=t.attributes;for(let i=r.length-1;i>=0;i--){const o=r.item(i).nodeName,s=r.item(i).nodeValue,l=JSON.parse('"'+s+'"');n.setAttribute(o,l)}t.parentNode.replaceChild(n,t)},async getlink(t){var e;this.isLoading=!0,this.activeId=t,this.activeTitle=((e=this.aukstructures.find(a=>a.id===t))==null?void 0:e.title)??"",this.frameHeight="320px",this.canScrollUp=!1,this.markVisited(t);try{const a=await W.get(V+"/api/getlink/"+t),n=Nt(a)||{},r=(n.aircraft||"").trim(),i=(n.auk||"").trim(),o=(n.file||"").trim();if(!r||!i||!o){this.contentHtml="",this.error="Для этого раздела не найден файл материала";return}this.error="";const s=await W.get(V+"/api/private/signed-url",{params:{aircraft:r,auk:i}}),u=(Nt(s)||{}).base||"";if(!u){this.contentHtml="",this.error="Не удалось получить доступ к материалу курса";return}const d=await W.get(u+encodeURIComponent(o).replace(/%2F/g,"/"),{optional:!0}),m=typeof d.data=="string"?d.data:"";this.contentHtml=m?'<base href="'+u+'" />'+m:"",this.link=this.contentHtml}catch(a){console.log(a),this.contentHtml="",this.error="Не удалось загрузить материал курса"}finally{this.isLoading=!1}},loadContent(t,e){this.isLoading=!0,this.getlink(t),setTimeout(()=>{const n=this.$refs.myIframe.contentDocument,r=new DOMParser().parseFromString(n.body.innerHTML,"text/html");try{e.forEach(i=>{const o=r.evaluate(i.originalXpath,r,null,XPathResult.FIRST_ORDERED_NODE_TYPE,null).singleNodeValue;if(o){const s=o.parentNode;s.innerHTML=i.highlightedText}})}catch(i){console.log(i)}n.body.innerHTML=r.documentElement.innerHTML,this.highlightNodes(n)},1e3),this.isLoading=!1},highlightNodes(t){this.highlighted=Array.from(t.querySelectorAll(".highlighted")),this.currentHighlight=0,this.scrollToHighlight(this.highlighted[this.currentHighlight])},scrollToHighlight(t){const e=this.$refs.myIframe,a=e.contentWindow.document,n=e.getBoundingClientRect(),i=t.getBoundingClientRect().top-n.top+a.documentElement.scrollTop;a.documentElement.scrollTop=i},scrollToNext(){this.highlighted.length>0?(this.currentHighlight=(this.currentHighlight+1)%this.highlighted.length,this.scrollToHighlight(this.highlighted[this.currentHighlight])):this.currentHighlight=0},scrollToPrev(){this.highlighted.length>0?(this.currentHighlight=(this.currentHighlight-1+this.highlighted.length)%this.highlighted.length,this.scrollToHighlight(this.highlighted[this.currentHighlight])):this.currentHighlight=0},showthumb(t){const e=document.getElementById(t);e&&(e.style.border="2px doted grey ",e.style.borderRadius="4px",t!==this.activeId&&(e.style.background="#D3D3D3"),e.style.transform="scale(1.03)")},hidethumb(t){const e=document.getElementById(t);e&&(e.style.border="none",t!==this.activeId&&(e.style.background="none"),e.style.transform="scale(1.0)")},getfirstauk:function(t){W.get(V+"/api/getfirstauk/"+t).then(e=>{this.firstId=Nt(e),this.getlink(this.firstId)})},toggleFavorite(){this.isFavorite=!this.isFavorite,this.isFavorite&&this.getFavorites()},toggleList(){this.showItems=!this.showItems},toggleSearch(){this.showSearch=!this.showSearch},addToFavorites(t){var a;const e=(a=this.aukstructures.find(n=>n.id===t))==null?void 0:a.title;W.post(V+"/api/favorites/add",{course_id:t,title:e}).then(n=>{this.getFavorites()}).catch(n=>{var i,o,s,l,u,d;const r=((s=(o=(i=n==null?void 0:n.response)==null?void 0:i.data)==null?void 0:o.error)==null?void 0:s.message)||((u=(l=n==null?void 0:n.response)==null?void 0:l.data)==null?void 0:u.error)||"";if(((d=n==null?void 0:n.response)==null?void 0:d.status)===400){this.alert=!0,this.alertType="info",this.snackbarText=typeof r=="string"&&r?r:this.$t("courseManifest.alreadyFavorite");return}this.alert=!0,this.alertType="error",this.snackbarText=this.$t("courseManifest.favoriteError")})},getFavorites(){W.get(V+"/api/favorites/").then(t=>{this.favorites=hn(t,"favorites")||[]})},removeFavorite(t){W.delete(V+`/api/favorites/${t}`).then(()=>{this.getFavorites()})},async search(){const t={query:this.searchTerm,path:this.path,aircraft:this.aircraft};if(this.searchTerm.length<3){this.snackbarText="..не меньше трех символов",this.alertType="error",this.alert=!0;return}W.post(V+"/api/search-files/",t).then(e=>{this.matchingFiles=ye(e)}).catch(e=>{console.log(e)}).finally(()=>{})}}},_s={class:"text-center",style:{fontSize:"20px"}},Fs={class:"cm-toolbar",role:"toolbar","aria-label":"Панель курса"},Ps={class:"cm-toolbar__group"},zs=["title","aria-pressed"],Cs={class:"cm-tool__label"},Es=["title","aria-pressed"],Os={class:"cm-tool__label"},Ts=["title","aria-pressed"],js={class:"cm-tool__label"},Ns=["disabled","title"],Ms={class:"cm-tool__label"},Ls={class:"cm-toolbar__meta"},Ds=["title"],$s={key:0,class:"cm-favorites"},Rs={class:"cm-favorites__head"},Ws={key:0,class:"cm-favorites__list"},Hs={class:"cm-favorites__title"},Us={key:1,class:"cm-favorites__empty"},Bs={key:0,class:"ml-2 mr-2 search-files__total-results"},Ys={key:1,class:"ml-5 mr-5 mt-1 search-files__no-results"},Xs=["data-type"],Vs=["title","onClick"],Gs={id:"iframe-container",style:{"border-radius":"8px"}},Ks={key:0,class:"has-text-danger px-3 py-2"},qs=["srcdoc"];function Js(t,e,a,n,r,i){const o=M("v-progress-linear"),s=M("v-sheet"),l=M("v-icon"),u=M("v-progress-circular"),d=M("v-btn"),m=M("v-text-field"),b=M("v-btn-group"),p=M("v-divider"),S=M("v-row"),y=M("v-col"),_=M("v-card"),g=M("AppToast");return C(),j(gt,null,[r.isLoading?(C(),jt(o,{key:0,color:"primary",indeterminate:""})):Y("",!0),e[11]||(e[11]=P("link",{rel:"stylesheet",href:"https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"},null,-1)),F(_,{color:"#f5f5f5"},{default:O(()=>[F(S,{dense:"","no-gutters":""},{default:O(()=>[F(y,{cols:"3"},{default:O(()=>[F(s,{class:"my-sheet cm-tree pa-2 mt-1",color:"#f5f5f5"},{default:O(()=>[F(s,{class:"mx-auto mt-0 mb-3",elevation:"4",rounded:"lg"},{default:O(()=>[P("div",_s,E(r.titleauk.toUpperCase()),1)]),_:1}),P("div",Fs,[P("div",Ps,[P("button",{type:"button",class:vt(["cm-tool",{"cm-tool--on":r.showItems}]),title:t.$t("courseManifest.showTree"),"aria-pressed":r.showItems,onClick:e[0]||(e[0]=(...c)=>i.toggleList&&i.toggleList(...c))},[F(l,{size:"22"},{default:O(()=>[X(E(r.showItems?"mdi-view-list":"mdi-view-list-outline"),1)]),_:1}),P("span",Cs,E(t.$t("courseManifest.tree")),1)],10,zs),P("button",{type:"button",class:vt(["cm-tool",{"cm-tool--on":r.isFavorite}]),title:t.$t("courseManifest.favorites"),"aria-pressed":r.isFavorite,onClick:e[1]||(e[1]=(...c)=>i.toggleFavorite&&i.toggleFavorite(...c))},[F(l,{size:"22"},{default:O(()=>[X(E(r.isFavorite?"mdi-heart":"mdi-heart-outline"),1)]),_:1}),P("span",Os,E(t.$t("courseManifest.favorite")),1)],10,Es),P("button",{type:"button",class:vt(["cm-tool",{"cm-tool--on":r.showSearch}]),title:t.$t("courseManifest.search"),"aria-pressed":r.showSearch,onClick:e[2]||(e[2]=(...c)=>i.toggleSearch&&i.toggleSearch(...c))},[F(l,{size:"22"},{default:O(()=>[X(E(r.showSearch?"mdi-magnify-minus-outline":"mdi-magnify"),1)]),_:1}),P("span",js,E(t.$t("courseManifest.search")),1)],10,Ts),P("button",{type:"button",class:"cm-tool",disabled:!r.activeId,title:t.$t("courseManifest.addFavorite"),onClick:e[3]||(e[3]=c=>i.addToFavorites(r.activeId))},[F(l,{size:"22"},{default:O(()=>[...e[8]||(e[8]=[X("mdi-bookmark-plus-outline",-1)])]),_:1}),P("span",Ms,E(t.$t("courseManifest.addFavoriteShort")),1)],8,Ns)]),P("div",Ls,[r.activeTitle?(C(),j("span",{key:0,class:"cm-toolbar__current",title:r.activeTitle},E(r.activeTitle),9,Ds)):Y("",!0),r.isLoading?(C(),jt(u,{key:1,indeterminate:"",size:"18",width:"2",color:"primary"})):Y("",!0)])]),r.isFavorite?(C(),j("div",$s,[P("div",Rs,[X(E(t.$t("courseManifest.favorites"))+" ",1),F(d,{size:"x-small",variant:"text",onClick:e[4]||(e[4]=c=>r.isFavorite=!1)},{default:O(()=>[X(E(t.$t("courseManifest.close")),1)]),_:1})]),r.favorites.length?(C(),j("ul",Ws,[(C(!0),j(gt,null,Mt(r.favorites,c=>(C(),j("li",{key:c.id,class:"cm-favorites__item"},[P("span",Hs,E(c.title),1),F(d,{icon:"mdi-close",size:"x-small",variant:"text",title:t.$t("courseManifest.removeFavorite"),onClick:x=>i.removeFavorite(c.course_id)},null,8,["title","onClick"])]))),128))])):(C(),j("p",Us,E(t.$t("courseManifest.favoritesEmpty")),1))])):Y("",!0),r.showSearch?(C(),jt(S,{key:1},{default:O(()=>[F(m,{class:"ml-5 mr-5",loading:r.loading,density:"compact",modelValue:r.searchTerm,"onUpdate:modelValue":e[5]||(e[5]=c=>r.searchTerm=c),variant:"outlined",rounded:"","append-inner-icon":"mdi-magnify",label:"Поиск","onClick:appendInner":i.search,onKeyup:xn(i.search,["enter"]),hint:"Введи искомый текст для поиска",clearable:"","single-line":""},null,8,["loading","modelValue","onClick:appendInner","onKeyup"]),P("div",null,[r.matchingFiles.length>0?(C(),j("ul",Bs,[X("Всего найдено: "+E(r.matchingFiles.length)+" ",1),F(b,null,{default:O(()=>[F(d,{onClick:i.scrollToPrev},{default:O(()=>[...e[9]||(e[9]=[P("span",null,"▲",-1)])]),_:1},8,["onClick"]),F(d,{onClick:i.scrollToNext},{default:O(()=>[...e[10]||(e[10]=[P("span",null,"▼",-1)])]),_:1},8,["onClick"])]),_:1}),F(p),(C(!0),j(gt,null,Mt(r.matchingFiles,c=>(C(),j("li",{key:c.file,style:{"white-space":"nowrap"}},[F(d,{onClick:x=>i.loadContent(c.itemId,c.highlightedNodes),class:"text-truncate",style:{"max-width":"100%",overflow:"hidden","text-overflow":"ellipsis"}},{default:O(()=>[X(E(c.title),1)]),_:2},1032,["onClick"])]))),128))])):(C(),j("p",Ys,E(t.$t("courseManifest.noResults")),1))])]),_:1})):Y("",!0),r.showItems?(C(!0),j(gt,{key:2},Mt(r.aukstructures,(c,x)=>(C(),j("div",{key:c.id,class:"auk-node",style:xe(i.nodeStyle(c)),"data-type":c.type},[x!==0?(C(),j("div",{key:0,class:vt(["auk-node__title",{"auk-node__title--module":c.type===3,"auk-node__title--active":c.id===r.activeId,"auk-node__title--visited":i.isVisited(c.id)}]),title:c.title,onClick:A=>c.type===3?i.openModule(c):""},E(c.title),11,Vs)):Y("",!0)],12,Xs))),128)):Y("",!0)]),_:1})]),_:1}),F(y,{cols:"9",class:"cm-content-col"},{default:O(()=>[F(s,{rounded:"",elevation:"5",class:"my-sheet cm-content pa-2 mt-2 mr-2",ref:"contentEl"},{default:O(()=>[wn(F(d,{class:"cm-scroll-top",color:"primary",size:"small",elevation:"6",icon:"mdi-arrow-up",title:t.$t("courseManifest.toTop"),onClick:i.scrollToTop},null,8,["title","onClick"]),[[Sn,r.canScrollUp]]),P("div",Gs,[r.error?(C(),j("p",Ks,E(r.error),1)):Y("",!0),P("iframe",{class:"hello px-5",srcdoc:r.contentHtml,ref:"myIframe",name:"iframe_a",onLoad:e[6]||(e[6]=(...c)=>i.onFrameLoad&&i.onFrameLoad(...c)),style:xe({height:r.frameHeight}),width:"100%",scrolling:"auto",title:"Материал курса"},null,44,qs)])]),_:1},512)]),_:1})]),_:1})]),_:1}),F(g,{modelValue:r.alert,"onUpdate:modelValue":e[7]||(e[7]=c=>r.alert=c),type:r.alertType,text:r.snackbarText},null,8,["modelValue","type","text"])],64)}const tl=vn(Is,[["render",Js]]);export{tl as default};
