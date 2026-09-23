import{E as cn,s as dn,B as Z,G as yt,_ as mn,g as M,c as Tt,i as ot,e as H,t as O,d as R,b as I,w as _,F as gt,D as V,j as gn,m as vn,a as j,o as E,v as jt,h as Nt,y as hn,H as ba}from"./app-COwdcWHH.js";import{p as pn}from"./Popup-CnjghvW4.js";/*!
 * Font Awesome Free 7.3.1 by @fontawesome - https://fontawesome.com
 * License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License)
 * Copyright 2026 Fonticons, Inc.
 */function Ut(t,a){(a==null||a>t.length)&&(a=t.length);for(var e=0,n=Array(a);e<a;e++)n[e]=t[e];return n}function bn(t){if(Array.isArray(t))return t}function yn(t){if(Array.isArray(t))return Ut(t)}function xn(t,a){if(!(t instanceof a))throw new TypeError("Cannot call a class as a function")}function wn(t,a){for(var e=0;e<a.length;e++){var n=a[e];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(t,Qa(n.key),n)}}function Sn(t,a,e){return a&&wn(t.prototype,a),Object.defineProperty(t,"prototype",{writable:!1}),t}function xt(t,a){var e=typeof Symbol<"u"&&t[Symbol.iterator]||t["@@iterator"];if(!e){if(Array.isArray(t)||(e=oa(t))||a){e&&(t=e);var n=0,r=function(){};return{s:r,n:function(){return n>=t.length?{done:!0}:{done:!1,value:t[n++]}},e:function(l){throw l},f:r}}throw new TypeError(`Invalid attempt to iterate non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}var i,o=!0,s=!1;return{s:function(){e=e.call(t)},n:function(){var l=e.next();return o=l.done,l},e:function(l){s=!0,i=l},f:function(){try{o||e.return==null||e.return()}finally{if(s)throw i}}}}function h(t,a,e){return(a=Qa(a))in t?Object.defineProperty(t,a,{value:e,enumerable:!0,configurable:!0,writable:!0}):t[a]=e,t}function kn(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function An(t,a){var e=t==null?null:typeof Symbol<"u"&&t[Symbol.iterator]||t["@@iterator"];if(e!=null){var n,r,i,o,s=[],l=!0,u=!1;try{if(i=(e=e.call(t)).next,a===0){if(Object(e)!==e)return;l=!1}else for(;!(l=(n=i.call(e)).done)&&(s.push(n.value),s.length!==a);l=!0);}catch(d){u=!0,r=d}finally{try{if(!l&&e.return!=null&&(o=e.return(),Object(o)!==o))return}finally{if(u)throw r}}return s}}function In(){throw new TypeError(`Invalid attempt to destructure non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function Fn(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function ya(t,a){var e=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);a&&(n=n.filter(function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable})),e.push.apply(e,n)}return e}function f(t){for(var a=1;a<arguments.length;a++){var e=arguments[a]!=null?arguments[a]:{};a%2?ya(Object(e),!0).forEach(function(n){h(t,n,e[n])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(e)):ya(Object(e)).forEach(function(n){Object.defineProperty(t,n,Object.getOwnPropertyDescriptor(e,n))})}return t}function Pt(t,a){return bn(t)||An(t,a)||oa(t,a)||In()}function D(t){return yn(t)||kn(t)||oa(t)||Fn()}function Pn(t,a){if(typeof t!="object"||!t)return t;var e=t[Symbol.toPrimitive];if(e!==void 0){var n=e.call(t,a);if(typeof n!="object")return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return(a==="string"?String:Number)(t)}function Qa(t){var a=Pn(t,"string");return typeof a=="symbol"?a:a+""}function kt(t){"@babel/helpers - typeof";return kt=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(a){return typeof a}:function(a){return a&&typeof Symbol=="function"&&a.constructor===Symbol&&a!==Symbol.prototype?"symbol":typeof a},kt(t)}function oa(t,a){if(t){if(typeof t=="string")return Ut(t,a);var e={}.toString.call(t).slice(8,-1);return e==="Object"&&t.constructor&&(e=t.constructor.name),e==="Map"||e==="Set"?Array.from(t):e==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(e)?Ut(t,a):void 0}}var xa=function(){},sa={},Za={},te=null,ae={mark:xa,measure:xa};try{typeof window<"u"&&(sa=window),typeof document<"u"&&(Za=document),typeof MutationObserver<"u"&&(te=MutationObserver),typeof performance<"u"&&(ae=performance)}catch{}var zn=sa.navigator||{},wa=zn.userAgent,Sa=wa===void 0?"":wa,Y=sa,A=Za,ka=te,vt=ae;Y.document;var U=!!A.documentElement&&!!A.head&&typeof A.addEventListener=="function"&&typeof A.createElement=="function",ee=~Sa.indexOf("MSIE")||~Sa.indexOf("Trident/"),ht,_n=/fa(k|kd|s|r|l|t|d|dr|dl|dt|b|slr|slpr|wsb|tl|ns|nds|es|gt|jr|jfr|jdr|usb|ufsb|udsb|cr|ss|sr|sl|st|sds|sdr|sdl|sdt|sldr|slpdr|pr|ms|vs)?[\-\ ]/,Cn=/Font ?Awesome ?([567 ]*)(Solid|Regular|Light|Thin|Duotone|Brands|Free|Pro|Sharp Duotone|Sharp|Kit|Notdog Duo|Notdog|Chisel|Etch|Graphite|Thumbprint|Jelly Fill|Jelly Duo|Jelly|Utility|Utility Fill|Utility Duo|Slab Press|Slab|Slab Duo|Slab Press Duo|Pixel|Mosaic|Vellum|Whiteboard)?.*/i,ne={classic:{fa:"solid",fas:"solid","fa-solid":"solid",far:"regular","fa-regular":"regular",fal:"light","fa-light":"light",fat:"thin","fa-thin":"thin",fab:"brands","fa-brands":"brands"},duotone:{fa:"solid",fad:"solid","fa-solid":"solid","fa-duotone":"solid",fadr:"regular","fa-regular":"regular",fadl:"light","fa-light":"light",fadt:"thin","fa-thin":"thin"},sharp:{fa:"solid",fass:"solid","fa-solid":"solid",fasr:"regular","fa-regular":"regular",fasl:"light","fa-light":"light",fast:"thin","fa-thin":"thin"},"sharp-duotone":{fa:"solid",fasds:"solid","fa-solid":"solid",fasdr:"regular","fa-regular":"regular",fasdl:"light","fa-light":"light",fasdt:"thin","fa-thin":"thin"},slab:{"fa-regular":"regular",faslr:"regular"},"slab-press":{"fa-regular":"regular",faslpr:"regular"},"slab-duo":{"fa-regular":"regular",fasldr:"regular"},"slab-press-duo":{"fa-regular":"regular",faslpdr:"regular"},thumbprint:{"fa-light":"light",fatl:"light"},vellum:{"fa-solid":"solid",favs:"solid"},pixel:{"fa-regular":"regular",fapr:"regular"},mosaic:{"fa-solid":"solid",fams:"solid"},whiteboard:{"fa-semibold":"semibold",fawsb:"semibold"},notdog:{"fa-solid":"solid",fans:"solid"},"notdog-duo":{"fa-solid":"solid",fands:"solid"},etch:{"fa-solid":"solid",faes:"solid"},graphite:{"fa-thin":"thin",fagt:"thin"},jelly:{"fa-regular":"regular",fajr:"regular"},"jelly-fill":{"fa-regular":"regular",fajfr:"regular"},"jelly-duo":{"fa-regular":"regular",fajdr:"regular"},chisel:{"fa-regular":"regular",facr:"regular"},utility:{"fa-semibold":"semibold",fausb:"semibold"},"utility-duo":{"fa-semibold":"semibold",faudsb:"semibold"},"utility-fill":{"fa-semibold":"semibold",faufsb:"semibold"}},On={GROUP:"duotone-group",PRIMARY:"primary",SECONDARY:"secondary"},re=["fa-classic","fa-duotone","fa-sharp","fa-sharp-duotone","fa-thumbprint","fa-whiteboard","fa-notdog","fa-notdog-duo","fa-chisel","fa-etch","fa-graphite","fa-jelly","fa-jelly-fill","fa-jelly-duo","fa-slab","fa-slab-press","fa-slab-press-duo","fa-slab-duo","fa-mosaic","fa-pixel","fa-vellum","fa-utility","fa-utility-duo","fa-utility-fill"],C="classic",ct="duotone",ie="sharp",oe="sharp-duotone",se="chisel",le="etch",fe="graphite",ue="jelly",ce="jelly-duo",de="jelly-fill",me="mosaic",ge="notdog",ve="notdog-duo",he="pixel",pe="slab",be="slab-duo",ye="slab-press",xe="slab-press-duo",we="thumbprint",Se="utility",ke="utility-duo",Ae="utility-fill",Ie="vellum",Fe="whiteboard",En="Classic",Tn="Duotone",jn="Sharp",Nn="Sharp Duotone",Dn="Chisel",Ln="Etch",Mn="Graphite",$n="Jelly",Rn="Jelly Duo",Wn="Jelly Fill",Bn="Mosaic",Un="Notdog",Hn="Notdog Duo",Yn="Pixel",Xn="Slab",Gn="Slab Duo",Vn="Slab Press",qn="Slab Press Duo",Kn="Thumbprint",Jn="Utility",Qn="Utility Duo",Zn="Utility Fill",tr="Vellum",ar="Whiteboard",Pe=[C,ct,ie,oe,se,le,fe,ue,ce,de,me,ge,ve,he,pe,be,ye,xe,we,Se,ke,Ae,Ie,Fe];ht={},h(h(h(h(h(h(h(h(h(h(ht,C,En),ct,Tn),ie,jn),oe,Nn),se,Dn),le,Ln),fe,Mn),ue,$n),ce,Rn),de,Wn),h(h(h(h(h(h(h(h(h(h(ht,me,Bn),ge,Un),ve,Hn),he,Yn),pe,Xn),be,Gn),ye,Vn),xe,qn),we,Kn),Se,Jn),h(h(h(h(ht,ke,Qn),Ae,Zn),Ie,tr),Fe,ar);var er={classic:{900:"fas",400:"far",normal:"far",300:"fal",100:"fat"},duotone:{900:"fad",400:"fadr",300:"fadl",100:"fadt"},sharp:{900:"fass",400:"fasr",300:"fasl",100:"fast"},"sharp-duotone":{900:"fasds",400:"fasdr",300:"fasdl",100:"fasdt"},slab:{400:"faslr"},"slab-press":{400:"faslpr"},"slab-duo":{400:"fasldr"},"slab-press-duo":{400:"faslpdr"},vellum:{900:"favs"},mosaic:{900:"fams"},pixel:{400:"fapr"},whiteboard:{600:"fawsb"},thumbprint:{300:"fatl"},notdog:{900:"fans"},"notdog-duo":{900:"fands"},etch:{900:"faes"},graphite:{100:"fagt"},chisel:{400:"facr"},jelly:{400:"fajr"},"jelly-fill":{400:"fajfr"},"jelly-duo":{400:"fajdr"},utility:{600:"fausb"},"utility-duo":{600:"faudsb"},"utility-fill":{600:"faufsb"}},nr={"Font Awesome 7 Free":{900:"fas",400:"far"},"Font Awesome 7 Pro":{900:"fas",400:"far",normal:"far",300:"fal",100:"fat"},"Font Awesome 7 Brands":{400:"fab",normal:"fab"},"Font Awesome 7 Duotone":{900:"fad",400:"fadr",normal:"fadr",300:"fadl",100:"fadt"},"Font Awesome 7 Sharp":{900:"fass",400:"fasr",normal:"fasr",300:"fasl",100:"fast"},"Font Awesome 7 Sharp Duotone":{900:"fasds",400:"fasdr",normal:"fasdr",300:"fasdl",100:"fasdt"},"Font Awesome 7 Jelly":{400:"fajr",normal:"fajr"},"Font Awesome 7 Jelly Fill":{400:"fajfr",normal:"fajfr"},"Font Awesome 7 Jelly Duo":{400:"fajdr",normal:"fajdr"},"Font Awesome 7 Slab":{400:"faslr",normal:"faslr"},"Font Awesome 7 Slab Press":{400:"faslpr",normal:"faslpr"},"Font Awesome 7 Slab Duo":{400:"fasldr",normal:"fasldr"},"Font Awesome 7 Slab Press Duo":{400:"faslpdr",normal:"faslpdr"},"Font Awesome 7 Pixel":{400:"fapr",normal:"fapr"},"Font Awesome 7 Mosaic":{900:"fams",normal:"fams"},"Font Awesome 7 Vellum":{900:"favs",normal:"favs"},"Font Awesome 7 Thumbprint":{300:"fatl",normal:"fatl"},"Font Awesome 7 Notdog":{900:"fans",normal:"fans"},"Font Awesome 7 Notdog Duo":{900:"fands",normal:"fands"},"Font Awesome 7 Etch":{900:"faes",normal:"faes"},"Font Awesome 7 Graphite":{100:"fagt",normal:"fagt"},"Font Awesome 7 Chisel":{400:"facr",normal:"facr"},"Font Awesome 7 Whiteboard":{600:"fawsb",normal:"fawsb"},"Font Awesome 7 Utility":{600:"fausb",normal:"fausb"},"Font Awesome 7 Utility Duo":{600:"faudsb",normal:"faudsb"},"Font Awesome 7 Utility Fill":{600:"faufsb",normal:"faufsb"}},rr=new Map([["classic",{defaultShortPrefixId:"fas",defaultStyleId:"solid",styleIds:["solid","regular","light","thin","brands"],futureStyleIds:[],defaultFontWeight:900}],["duotone",{defaultShortPrefixId:"fad",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["sharp",{defaultShortPrefixId:"fass",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["sharp-duotone",{defaultShortPrefixId:"fasds",defaultStyleId:"solid",styleIds:["solid","regular","light","thin"],futureStyleIds:[],defaultFontWeight:900}],["chisel",{defaultShortPrefixId:"facr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["etch",{defaultShortPrefixId:"faes",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["graphite",{defaultShortPrefixId:"fagt",defaultStyleId:"thin",styleIds:["thin"],futureStyleIds:[],defaultFontWeight:100}],["jelly",{defaultShortPrefixId:"fajr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["jelly-duo",{defaultShortPrefixId:"fajdr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["jelly-fill",{defaultShortPrefixId:"fajfr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["mosaic",{defaultShortPrefixId:"fams",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["notdog",{defaultShortPrefixId:"fans",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["notdog-duo",{defaultShortPrefixId:"fands",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["pixel",{defaultShortPrefixId:"fapr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab",{defaultShortPrefixId:"faslr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab-duo",{defaultShortPrefixId:"fasldr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab-press",{defaultShortPrefixId:"faslpr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["slab-press-duo",{defaultShortPrefixId:"faslpdr",defaultStyleId:"regular",styleIds:["regular"],futureStyleIds:[],defaultFontWeight:400}],["thumbprint",{defaultShortPrefixId:"fatl",defaultStyleId:"light",styleIds:["light"],futureStyleIds:[],defaultFontWeight:300}],["utility",{defaultShortPrefixId:"fausb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["utility-duo",{defaultShortPrefixId:"faudsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["utility-fill",{defaultShortPrefixId:"faufsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}],["vellum",{defaultShortPrefixId:"favs",defaultStyleId:"solid",styleIds:["solid"],futureStyleIds:[],defaultFontWeight:900}],["whiteboard",{defaultShortPrefixId:"fawsb",defaultStyleId:"semibold",styleIds:["semibold"],futureStyleIds:[],defaultFontWeight:600}]]),ir={chisel:{regular:"facr"},classic:{brands:"fab",light:"fal",regular:"far",solid:"fas",thin:"fat"},duotone:{light:"fadl",regular:"fadr",solid:"fad",thin:"fadt"},etch:{solid:"faes"},graphite:{thin:"fagt"},jelly:{regular:"fajr"},"jelly-duo":{regular:"fajdr"},"jelly-fill":{regular:"fajfr"},mosaic:{solid:"fams"},notdog:{solid:"fans"},"notdog-duo":{solid:"fands"},pixel:{regular:"fapr"},sharp:{light:"fasl",regular:"fasr",solid:"fass",thin:"fast"},"sharp-duotone":{light:"fasdl",regular:"fasdr",solid:"fasds",thin:"fasdt"},slab:{regular:"faslr"},"slab-duo":{regular:"fasldr"},"slab-press":{regular:"faslpr"},"slab-press-duo":{regular:"faslpdr"},thumbprint:{light:"fatl"},utility:{semibold:"fausb"},"utility-duo":{semibold:"faudsb"},"utility-fill":{semibold:"faufsb"},vellum:{solid:"favs"},whiteboard:{semibold:"fawsb"}},ze=["fak","fa-kit","fakd","fa-kit-duotone"],Aa={kit:{fak:"kit","fa-kit":"kit"},"kit-duotone":{fakd:"kit-duotone","fa-kit-duotone":"kit-duotone"}},or=["kit"],sr="kit",lr="kit-duotone",fr="Kit",ur="Kit Duotone";h(h({},sr,fr),lr,ur);var cr={kit:{"fa-kit":"fak"}},dr={"Font Awesome Kit":{400:"fak",normal:"fak"},"Font Awesome Kit Duotone":{400:"fakd",normal:"fakd"}},mr={kit:{fak:"fa-kit"}},Ia={kit:{kit:"fak"},"kit-duotone":{"kit-duotone":"fakd"}},pt,bt={GROUP:"duotone-group",SWAP_OPACITY:"swap-opacity",PRIMARY:"primary",SECONDARY:"secondary"},gr=["fa-classic","fa-duotone","fa-sharp","fa-sharp-duotone","fa-thumbprint","fa-whiteboard","fa-notdog","fa-notdog-duo","fa-chisel","fa-etch","fa-graphite","fa-jelly","fa-jelly-fill","fa-jelly-duo","fa-slab","fa-slab-press","fa-slab-press-duo","fa-slab-duo","fa-mosaic","fa-pixel","fa-vellum","fa-utility","fa-utility-duo","fa-utility-fill"],vr="classic",hr="duotone",pr="sharp",br="sharp-duotone",yr="chisel",xr="etch",wr="graphite",Sr="jelly",kr="jelly-duo",Ar="jelly-fill",Ir="mosaic",Fr="notdog",Pr="notdog-duo",zr="pixel",_r="slab",Cr="slab-duo",Or="slab-press",Er="slab-press-duo",Tr="thumbprint",jr="utility",Nr="utility-duo",Dr="utility-fill",Lr="vellum",Mr="whiteboard",$r="Classic",Rr="Duotone",Wr="Sharp",Br="Sharp Duotone",Ur="Chisel",Hr="Etch",Yr="Graphite",Xr="Jelly",Gr="Jelly Duo",Vr="Jelly Fill",qr="Mosaic",Kr="Notdog",Jr="Notdog Duo",Qr="Pixel",Zr="Slab",ti="Slab Duo",ai="Slab Press",ei="Slab Press Duo",ni="Thumbprint",ri="Utility",ii="Utility Duo",oi="Utility Fill",si="Vellum",li="Whiteboard";pt={},h(h(h(h(h(h(h(h(h(h(pt,vr,$r),hr,Rr),pr,Wr),br,Br),yr,Ur),xr,Hr),wr,Yr),Sr,Xr),kr,Gr),Ar,Vr),h(h(h(h(h(h(h(h(h(h(pt,Ir,qr),Fr,Kr),Pr,Jr),zr,Qr),_r,Zr),Cr,ti),Or,ai),Er,ei),Tr,ni),jr,ri),h(h(h(h(pt,Nr,ii),Dr,oi),Lr,si),Mr,li);var fi="kit",ui="kit-duotone",ci="Kit",di="Kit Duotone";h(h({},fi,ci),ui,di);var mi={classic:{"fa-brands":"fab","fa-duotone":"fad","fa-light":"fal","fa-regular":"far","fa-solid":"fas","fa-thin":"fat"},duotone:{"fa-regular":"fadr","fa-light":"fadl","fa-thin":"fadt"},sharp:{"fa-solid":"fass","fa-regular":"fasr","fa-light":"fasl","fa-thin":"fast"},"sharp-duotone":{"fa-solid":"fasds","fa-regular":"fasdr","fa-light":"fasdl","fa-thin":"fasdt"},slab:{"fa-regular":"faslr"},"slab-press":{"fa-regular":"faslpr"},"slab-duo":{"fa-regular":"fasldr"},"slab-press-duo":{"fa-regular":"faslpdr"},pixel:{"fa-regular":"fapr"},mosaic:{"fa-solid":"fams"},vellum:{"fa-solid":"favs"},whiteboard:{"fa-semibold":"fawsb"},thumbprint:{"fa-light":"fatl"},notdog:{"fa-solid":"fans"},"notdog-duo":{"fa-solid":"fands"},etch:{"fa-solid":"faes"},graphite:{"fa-thin":"fagt"},jelly:{"fa-regular":"fajr"},"jelly-fill":{"fa-regular":"fajfr"},"jelly-duo":{"fa-regular":"fajdr"},chisel:{"fa-regular":"facr"},utility:{"fa-semibold":"fausb"},"utility-duo":{"fa-semibold":"faudsb"},"utility-fill":{"fa-semibold":"faufsb"}},gi={classic:["fas","far","fal","fat","fad"],duotone:["fadr","fadl","fadt"],sharp:["fass","fasr","fasl","fast"],"sharp-duotone":["fasds","fasdr","fasdl","fasdt"],slab:["faslr"],"slab-press":["faslpr"],"slab-duo":["fasldr"],"slab-press-duo":["faslpdr"],pixel:["fapr"],mosaic:["fams"],vellum:["favs"],whiteboard:["fawsb"],thumbprint:["fatl"],notdog:["fans"],"notdog-duo":["fands"],etch:["faes"],graphite:["fagt"],jelly:["fajr"],"jelly-fill":["fajfr"],"jelly-duo":["fajdr"],chisel:["facr"],utility:["fausb"],"utility-duo":["faudsb"],"utility-fill":["faufsb"]},Ht={classic:{fab:"fa-brands",fad:"fa-duotone",fal:"fa-light",far:"fa-regular",fas:"fa-solid",fat:"fa-thin"},duotone:{fadr:"fa-regular",fadl:"fa-light",fadt:"fa-thin"},sharp:{fass:"fa-solid",fasr:"fa-regular",fasl:"fa-light",fast:"fa-thin"},"sharp-duotone":{fasds:"fa-solid",fasdr:"fa-regular",fasdl:"fa-light",fasdt:"fa-thin"},slab:{faslr:"fa-regular"},"slab-press":{faslpr:"fa-regular"},"slab-duo":{fasldr:"fa-regular"},"slab-press-duo":{faslpdr:"fa-regular"},pixel:{fapr:"fa-regular"},mosaic:{fams:"fa-solid"},vellum:{favs:"fa-solid"},whiteboard:{fawsb:"fa-semibold"},thumbprint:{fatl:"fa-light"},notdog:{fans:"fa-solid"},"notdog-duo":{fands:"fa-solid"},etch:{faes:"fa-solid"},graphite:{fagt:"fa-thin"},jelly:{fajr:"fa-regular"},"jelly-fill":{fajfr:"fa-regular"},"jelly-duo":{fajdr:"fa-regular"},chisel:{facr:"fa-regular"},utility:{fausb:"fa-semibold"},"utility-duo":{faudsb:"fa-semibold"},"utility-fill":{faufsb:"fa-semibold"}},vi=["fa-solid","fa-regular","fa-light","fa-thin","fa-duotone","fa-brands","fa-semibold"],_e=["fa","fas","far","fal","fat","fad","fadr","fadl","fadt","fab","fass","fasr","fasl","fast","fasds","fasdr","fasdl","fasdt","faslr","faslpr","fasldr","faslpdr","fapr","fams","favs","fawsb","fatl","fans","fands","faes","fagt","fajr","fajfr","fajdr","facr","fausb","faudsb","faufsb"].concat(gr,vi),hi=["solid","regular","light","thin","duotone","brands","semibold"],Ce=[1,2,3,4,5,6,7,8,9,10],pi=Ce.concat([11,12,13,14,15,16,17,18,19,20]),bi=["aw","fw","pull-left","pull-right"],yi=[].concat(D(Object.keys(gi)),hi,bi,["2xs","xs","sm","lg","xl","2xl","beat","beat-fade","border","bounce","buzz","canvas-square","canvas-roomy","fade","flip-360","flip-both","flip-horizontal","flip-vertical","flip","float","inverse","jello","layers","layers-bottom-left","layers-bottom-right","layers-counter","layers-text","layers-top-left","layers-top-right","li","pull-end","pull-start","pulse","rotate-180","rotate-270","rotate-90","rotate-by","shake","spin-pulse","spin-reverse","spin","spin-snap","spin-snap-4","spin-snap-8","stack-1x","stack-2x","stack","swing","ul","wag","width-auto","width-fixed",bt.GROUP,bt.SWAP_OPACITY,bt.PRIMARY,bt.SECONDARY]).concat(Ce.map(function(t){return"".concat(t,"x")})).concat(pi.map(function(t){return"w-".concat(t)})),xi={"Font Awesome 5 Free":{900:"fas",400:"far"},"Font Awesome 5 Pro":{900:"fas",400:"far",normal:"far",300:"fal"},"Font Awesome 5 Brands":{400:"fab",normal:"fab"},"Font Awesome 5 Duotone":{900:"fad"}},W="___FONT_AWESOME___",Yt=16,Oe="fa",Ee="svg-inline--fa",J="data-fa-i2svg",Xt="data-fa-pseudo-element",wi="data-fa-pseudo-element-pending",la="data-prefix",fa="data-icon",Fa="fontawesome-i2svg",Si="async",ki=["HTML","HEAD","STYLE","SCRIPT"],Te=["::before","::after",":before",":after"],je=function(){try{return!0}catch{return!1}}();function dt(t){return new Proxy(t,{get:function(e,n){return n in e?e[n]:e[C]}})}var Ne=f({},ne);Ne[C]=f(f(f(f({},{"fa-duotone":"duotone"}),ne[C]),Aa.kit),Aa["kit-duotone"]);var Ai=dt(Ne),Gt=f({},ir);Gt[C]=f(f(f(f({},{duotone:"fad"}),Gt[C]),Ia.kit),Ia["kit-duotone"]);var Pa=dt(Gt),Vt=f({},Ht);Vt[C]=f(f({},Vt[C]),mr.kit);var ua=dt(Vt),qt=f({},mi);qt[C]=f(f({},qt[C]),cr.kit);dt(qt);var Ii=_n,De="fa-layers-text",Fi=Cn,Pi=f({},er);dt(Pi);var zi=["class","data-prefix","data-icon","data-fa-transform","data-fa-mask"],Dt=On,_i=[].concat(D(or),D(yi)),lt=Y.FontAwesomeConfig||{};function Ci(t){var a=A.querySelector("script["+t+"]");if(a)return a.getAttribute(t)}function Oi(t){return t===""?!0:t==="false"?!1:t==="true"?!0:t}if(A&&typeof A.querySelector=="function"){var Ei=[["data-family-prefix","familyPrefix"],["data-css-prefix","cssPrefix"],["data-family-default","familyDefault"],["data-style-default","styleDefault"],["data-replacement-class","replacementClass"],["data-auto-replace-svg","autoReplaceSvg"],["data-auto-add-css","autoAddCss"],["data-search-pseudo-elements","searchPseudoElements"],["data-search-pseudo-elements-warnings","searchPseudoElementsWarnings"],["data-search-pseudo-elements-full-scan","searchPseudoElementsFullScan"],["data-observe-mutations","observeMutations"],["data-mutate-approach","mutateApproach"],["data-keep-original-source","keepOriginalSource"],["data-measure-performance","measurePerformance"],["data-show-missing-icons","showMissingIcons"]];Ei.forEach(function(t){var a=Pt(t,2),e=a[0],n=a[1],r=Oi(Ci(e));r!=null&&(lt[n]=r)})}var Le={styleDefault:"solid",familyDefault:C,cssPrefix:Oe,replacementClass:Ee,autoReplaceSvg:!0,autoAddCss:!0,searchPseudoElements:!1,searchPseudoElementsWarnings:!0,searchPseudoElementsFullScan:!1,observeMutations:!0,mutateApproach:"async",keepOriginalSource:!0,measurePerformance:!1,showMissingIcons:!0};lt.familyPrefix&&(lt.cssPrefix=lt.familyPrefix);var nt=f(f({},Le),lt);nt.autoReplaceSvg||(nt.observeMutations=!1);var v={};Object.keys(Le).forEach(function(t){Object.defineProperty(v,t,{enumerable:!0,set:function(e){nt[t]=e,ft.forEach(function(n){return n(v)})},get:function(){return nt[t]}})});Object.defineProperty(v,"familyPrefix",{enumerable:!0,set:function(a){nt.cssPrefix=a,ft.forEach(function(e){return e(v)})},get:function(){return nt.cssPrefix}});Y.FontAwesomeConfig=v;var ft=[];function Ti(t){return ft.push(t),function(){ft.splice(ft.indexOf(t),1)}}var tt=Yt,$={size:16,x:0,y:0,rotate:0,flipX:!1,flipY:!1};function ji(t){if(!(!t||!U)){var a=A.createElement("style");a.setAttribute("type","text/css"),a.innerHTML=t;for(var e=A.head.childNodes,n=null,r=e.length-1;r>-1;r--){var i=e[r],o=(i.tagName||"").toUpperCase();["STYLE","LINK"].indexOf(o)>-1&&(n=i)}return A.head.insertBefore(a,n),t}}var Ni="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";function za(){for(var t=12,a="";t-- >0;)a+=Ni[Math.random()*62|0];return a}function rt(t){for(var a=[],e=(t||[]).length>>>0;e--;)a[e]=t[e];return a}function ca(t){return t.classList?rt(t.classList):(t.getAttribute("class")||"").split(" ").filter(function(a){return a})}function Me(t){return"".concat(t).replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/'/g,"&#39;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}function Di(t){return Object.keys(t||{}).reduce(function(a,e){return a+"".concat(e,'="').concat(Me(t[e]),'" ')},"").trim()}function zt(t){return Object.keys(t||{}).reduce(function(a,e){return a+"".concat(e,": ").concat(t[e].trim(),";")},"")}function da(t){return t.size!==$.size||t.x!==$.x||t.y!==$.y||t.rotate!==$.rotate||t.flipX||t.flipY}function Li(t){var a=t.transform,e=t.containerWidth,n=t.iconWidth,r={transform:"translate(".concat(e/2," 256)")},i="translate(".concat(a.x*32,", ").concat(a.y*32,") "),o="scale(".concat(a.size/16*(a.flipX?-1:1),", ").concat(a.size/16*(a.flipY?-1:1),") "),s="rotate(".concat(a.rotate," 0 0)"),l={transform:"".concat(i," ").concat(o," ").concat(s)},u={transform:"translate(".concat(n/2*-1," -256)")};return{outer:r,inner:l,path:u}}function Mi(t){var a=t.transform,e=t.width,n=e===void 0?Yt:e,r=t.height,i=r===void 0?Yt:r,o="";return ee?o+="translate(".concat(a.x/tt-n/2,"em, ").concat(a.y/tt-i/2,"em) "):o+="translate(calc(-50% + ".concat(a.x/tt,"em), calc(-50% + ").concat(a.y/tt,"em)) "),o+="scale(".concat(a.size/tt*(a.flipX?-1:1),", ").concat(a.size/tt*(a.flipY?-1:1),") "),o+="rotate(".concat(a.rotate,"deg) "),o}var $i=`:root, :host {
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
}`;function $e(){var t=Oe,a=Ee,e=v.cssPrefix,n=v.replacementClass,r=$i;if(e!==t||n!==a){var i=new RegExp("\\.".concat(t,"\\-"),"g"),o=new RegExp("\\--".concat(t,"\\-"),"g"),s=new RegExp("\\.".concat(a),"g");r=r.replace(i,".".concat(e,"-")).replace(o,"--".concat(e,"-")).replace(s,".".concat(n))}return r}var _a=!1;function Lt(){v.autoAddCss&&!_a&&(ji($e()),_a=!0)}var Ri={mixout:function(){return{dom:{css:$e,insertCss:Lt}}},hooks:function(){return{beforeDOMElementCreation:function(){Lt()},beforeI2svg:function(){Lt()}}}},B=Y||{};B[W]||(B[W]={});B[W].styles||(B[W].styles={});B[W].hooks||(B[W].hooks={});B[W].shims||(B[W].shims=[]);var N=B[W],Re=[],We=function(){A.removeEventListener("DOMContentLoaded",We),At=1,Re.map(function(a){return a()})},At=!1;U&&(At=(A.documentElement.doScroll?/^loaded|^c/:/^loaded|^i|^c/).test(A.readyState),At||A.addEventListener("DOMContentLoaded",We));function Wi(t){U&&(At?setTimeout(t,0):Re.push(t))}function mt(t){var a=t.tag,e=t.attributes,n=e===void 0?{}:e,r=t.children,i=r===void 0?[]:r;return typeof t=="string"?Me(t):"<".concat(a," ").concat(Di(n),">").concat(i.map(mt).join(""),"</").concat(a,">")}function Ca(t,a,e){if(t&&t[a]&&t[a][e])return{prefix:a,iconName:e,icon:t[a][e]}}var Mt=function(a,e,n,r){var i=Object.keys(a),o=i.length,s=e,l,u,d;for(n===void 0?(l=1,d=a[i[0]]):(l=0,d=n);l<o;l++)u=i[l],d=s(d,a[u],u,a);return d};function Be(t){return D(t).length!==1?null:t.codePointAt(0).toString(16)}function Oa(t){return Object.keys(t).reduce(function(a,e){var n=t[e],r=!!n.icon;return r?a[n.iconName]=n.icon:a[e]=n,a},{})}function Kt(t,a){var e=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{},n=e.skipHooks,r=n===void 0?!1:n,i=Oa(a);typeof N.hooks.addPack=="function"&&!r?N.hooks.addPack(t,Oa(a)):N.styles[t]=f(f({},N.styles[t]||{}),i),t==="fas"&&Kt("fa",a)}var ut=N.styles,Bi=N.shims,Ue=Object.keys(ua),Ui=Ue.reduce(function(t,a){return t[a]=Object.keys(ua[a]),t},{}),ma=null,He={},Ye={},Xe={},Ge={},Ve={};function Hi(t){return~_i.indexOf(t)}function Yi(t,a){var e=a.split("-"),n=e[0],r=e.slice(1).join("-");return n===t&&r!==""&&!Hi(r)?r:null}var qe=function(){var a=function(i){return Mt(ut,function(o,s,l){return o[l]=Mt(s,i,{}),o},{})};He=a(function(r,i,o){if(i[3]&&(r[i[3]]=o),i[2]){var s=i[2].filter(function(l){return typeof l=="number"});s.forEach(function(l){r[l.toString(16)]=o})}return r}),Ye=a(function(r,i,o){if(r[o]=o,i[2]){var s=i[2].filter(function(l){return typeof l=="string"});s.forEach(function(l){r[l]=o})}return r}),Ve=a(function(r,i,o){var s=i[2];return r[o]=o,s.forEach(function(l){r[l]=o}),r});var e="far"in ut||v.autoFetchSvg,n=Mt(Bi,function(r,i){var o=i[0],s=i[1],l=i[2];return s==="far"&&!e&&(s="fas"),typeof o=="string"&&(r.names[o]={prefix:s,iconName:l}),typeof o=="number"&&(r.unicodes[o.toString(16)]={prefix:s,iconName:l}),r},{names:{},unicodes:{}});Xe=n.names,Ge=n.unicodes,ma=_t(v.styleDefault,{family:v.familyDefault})};Ti(function(t){ma=_t(t.styleDefault,{family:v.familyDefault})});qe();function ga(t,a){return(He[t]||{})[a]}function Xi(t,a){return(Ye[t]||{})[a]}function K(t,a){return(Ve[t]||{})[a]}function Ke(t){return Xe[t]||{prefix:null,iconName:null}}function Gi(t){var a=Ge[t],e=ga("fas",t);return a||(e?{prefix:"fas",iconName:e}:null)||{prefix:null,iconName:null}}function X(){return ma}var Je=function(){return{prefix:null,iconName:null,rest:[]}};function Vi(t){var a=C,e=Ue.reduce(function(n,r){return n[r]="".concat(v.cssPrefix,"-").concat(r),n},{});return Pe.forEach(function(n){(t.includes(e[n])||t.some(function(r){return Ui[n].includes(r)}))&&(a=n)}),a}function _t(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},e=a.family,n=e===void 0?C:e,r=Ai[n][t];if(n===ct&&!t)return"fad";var i=Pa[n][t]||Pa[n][r],o=t in N.styles?t:null,s=i||o||null;return s}function qi(t){var a=[],e=null;return t.forEach(function(n){var r=Yi(v.cssPrefix,n);r?e=r:n&&a.push(n)}),{iconName:e,rest:a}}function Ea(t){return t.sort().filter(function(a,e,n){return n.indexOf(a)===e})}var Ta=_e.concat(ze);function Ct(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},e=a.skipLookups,n=e===void 0?!1:e,r=null,i=Ea(t.filter(function(p){return Ta.includes(p)})),o=Ea(t.filter(function(p){return!Ta.includes(p)})),s=i.filter(function(p){return r=p,!re.includes(p)}),l=Pt(s,1),u=l[0],d=u===void 0?null:u,m=Vi(i),b=f(f({},qi(o)),{},{prefix:_t(d,{family:m})});return f(f(f({},b),Zi({values:t,family:m,styles:ut,config:v,canonical:b,givenPrefix:r})),Ki(n,r,b))}function Ki(t,a,e){var n=e.prefix,r=e.iconName;if(t||!n||!r)return{prefix:n,iconName:r};var i=a==="fa"?Ke(r):{},o=K(n,r);return r=i.iconName||o||r,n=i.prefix||n,n==="far"&&!ut.far&&ut.fas&&!v.autoFetchSvg&&(n="fas"),{prefix:n,iconName:r}}var Ji=Pe.filter(function(t){return t!==C||t!==ct}),Qi=Object.keys(Ht).filter(function(t){return t!==C}).map(function(t){return Object.keys(Ht[t])}).flat();function Zi(t){var a=t.values,e=t.family,n=t.canonical,r=t.givenPrefix,i=r===void 0?"":r,o=t.styles,s=o===void 0?{}:o,l=t.config,u=l===void 0?{}:l,d=e===ct,m=a.includes("fa-duotone")||a.includes("fad"),b=u.familyDefault==="duotone",p=n.prefix==="fad"||n.prefix==="fa-duotone";if(!d&&(m||b||p)&&(n.prefix="fad"),(a.includes("fa-brands")||a.includes("fab"))&&(n.prefix="fab"),!n.prefix&&Ji.includes(e)){var k=Object.keys(s).find(function(P){return Qi.includes(P)});if(k||u.autoFetchSvg){var y=rr.get(e).defaultShortPrefixId;n.prefix=y,n.iconName=K(n.prefix,n.iconName)||n.iconName}}return(n.prefix==="fa"||i==="fa")&&(n.prefix=X()||"fas"),n}var to=function(){function t(){xn(this,t),this.definitions={}}return Sn(t,[{key:"add",value:function(){for(var e=this,n=arguments.length,r=new Array(n),i=0;i<n;i++)r[i]=arguments[i];var o=r.reduce(this._pullDefinitions,{});Object.keys(o).forEach(function(s){e.definitions[s]=f(f({},e.definitions[s]||{}),o[s]),Kt(s,o[s]);var l=ua[C][s];l&&Kt(l,o[s]),qe()})}},{key:"reset",value:function(){this.definitions={}}},{key:"_pullDefinitions",value:function(e,n){var r=n.prefix&&n.iconName&&n.icon?{0:n}:n;return Object.keys(r).map(function(i){var o=r[i],s=o.prefix,l=o.iconName,u=o.icon,d=u[2];e[s]||(e[s]={}),d.length>0&&d.forEach(function(m){typeof m=="string"&&(e[s][m]=u)}),e[s][l]=u}),e}}])}(),ja=[],at={},et={},ao=Object.keys(et);function eo(t,a){var e=a.mixoutsTo;return ja=t,at={},Object.keys(et).forEach(function(n){ao.indexOf(n)===-1&&delete et[n]}),ja.forEach(function(n){var r=n.mixout?n.mixout():{};if(Object.keys(r).forEach(function(o){typeof r[o]=="function"&&(e[o]=r[o]),kt(r[o])==="object"&&Object.keys(r[o]).forEach(function(s){e[o]||(e[o]={}),e[o][s]=r[o][s]})}),n.hooks){var i=n.hooks();Object.keys(i).forEach(function(o){at[o]||(at[o]=[]),at[o].push(i[o])})}n.provides&&n.provides(et)}),e}function Jt(t,a){for(var e=arguments.length,n=new Array(e>2?e-2:0),r=2;r<e;r++)n[r-2]=arguments[r];var i=at[t]||[];return i.forEach(function(o){a=o.apply(null,[a].concat(n))}),a}function Q(t){for(var a=arguments.length,e=new Array(a>1?a-1:0),n=1;n<a;n++)e[n-1]=arguments[n];var r=at[t]||[];r.forEach(function(i){i.apply(null,e)})}function G(){var t=arguments[0],a=Array.prototype.slice.call(arguments,1);return et[t]?et[t].apply(null,a):void 0}function Qt(t){t.prefix==="fa"&&(t.prefix="fas");var a=t.iconName,e=t.prefix||X();if(a)return a=K(e,a)||a,Ca(Qe.definitions,e,a)||Ca(N.styles,e,a)}var Qe=new to,no=function(){v.autoReplaceSvg=!1,v.observeMutations=!1,Q("noAuto")},ro={i2svg:function(){var a=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{};return U?(Q("beforeI2svg",a),G("pseudoElements2svg",a),G("i2svg",a)):Promise.reject(new Error("Operation requires a DOM of some kind."))},watch:function(){var a=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{},e=a.autoReplaceSvgRoot;v.autoReplaceSvg===!1&&(v.autoReplaceSvg=!0),v.observeMutations=!0,Wi(function(){oo({autoReplaceSvgRoot:e}),Q("watch",a)})}},io={icon:function(a){if(a===null)return null;if(kt(a)==="object"&&a.prefix&&a.iconName)return{prefix:a.prefix,iconName:K(a.prefix,a.iconName)||a.iconName};if(Array.isArray(a)&&a.length===2){var e=a[1].indexOf("fa-")===0?a[1].slice(3):a[1],n=_t(a[0]);return{prefix:n,iconName:K(n,e)||e}}if(typeof a=="string"&&(a.indexOf("".concat(v.cssPrefix,"-"))>-1||a.match(Ii))){var r=Ct(a.split(" "),{skipLookups:!0});return{prefix:r.prefix||X(),iconName:K(r.prefix,r.iconName)||r.iconName}}if(typeof a=="string"){var i=X();return{prefix:i,iconName:K(i,a)||a}}}},T={noAuto:no,config:v,dom:ro,parse:io,library:Qe,findIconDefinition:Qt,toHtml:mt},oo=function(){var a=arguments.length>0&&arguments[0]!==void 0?arguments[0]:{},e=a.autoReplaceSvgRoot,n=e===void 0?A:e;(Object.keys(N.styles).length>0||v.autoFetchSvg)&&U&&v.autoReplaceSvg&&T.dom.i2svg({node:n})};function Ot(t,a){return Object.defineProperty(t,"abstract",{get:a}),Object.defineProperty(t,"html",{get:function(){return t.abstract.map(function(n){return mt(n)})}}),Object.defineProperty(t,"node",{get:function(){if(U){var n=A.createElement("div");return n.innerHTML=t.html,n.children}}}),t}function so(t){var a=t.children,e=t.main,n=t.mask,r=t.attributes,i=t.styles,o=t.transform;if(da(o)&&e.found&&!n.found){var s=e.width,l=e.height,u={x:s/l/2,y:.5};r.style=zt(f(f({},i),{},{"transform-origin":"".concat(u.x+o.x/16,"em ").concat(u.y+o.y/16,"em")}))}return[{tag:"svg",attributes:r,children:a}]}function lo(t){var a=t.prefix,e=t.iconName,n=t.children,r=t.attributes,i=t.symbol,o=i===!0?"".concat(a,"-").concat(v.cssPrefix,"-").concat(e):i;return[{tag:"svg",attributes:{style:"display: none;"},children:[{tag:"symbol",attributes:f(f({},r),{},{id:o}),children:n}]}]}function fo(t){var a=["aria-label","aria-labelledby","title","role"];return a.some(function(e){return e in t})}function va(t){var a=t.icons,e=a.main,n=a.mask,r=t.prefix,i=t.iconName,o=t.transform,s=t.symbol,l=t.maskId,u=t.extra,d=t.watchable,m=d===void 0?!1:d,b=n.found?n:e,p=b.width,k=b.height,y=[v.replacementClass,i?"".concat(v.cssPrefix,"-").concat(i):""].filter(function(z){return u.classes.indexOf(z)===-1}).filter(function(z){return z!==""||!!z}).concat(u.classes).join(" "),P={children:[],attributes:f(f({},u.attributes),{},{"data-prefix":r,"data-icon":i,class:y,role:u.attributes.role||"img",viewBox:"0 0 ".concat(p," ").concat(k)})};!fo(u.attributes)&&!u.attributes["aria-hidden"]&&(P.attributes["aria-hidden"]="true"),m&&(P.attributes[J]="");var g=f(f({},P),{},{prefix:r,iconName:i,main:e,mask:n,maskId:l,transform:o,symbol:s,styles:f({},u.styles)}),c=n.found&&e.found?G("generateAbstractMask",g)||{children:[],attributes:{}}:G("generateAbstractIcon",g)||{children:[],attributes:{}},x=c.children,S=c.attributes;return g.children=x,g.attributes=S,s?lo(g):so(g)}function Na(t){var a=t.content,e=t.width,n=t.height,r=t.transform,i=t.extra,o=t.watchable,s=o===void 0?!1:o,l=f(f({},i.attributes),{},{class:i.classes.join(" ")});s&&(l[J]="");var u=f({},i.styles);da(r)&&(u.transform=Mi({transform:r,width:e,height:n}),u["-webkit-transform"]=u.transform);var d=zt(u);d.length>0&&(l.style=d);var m=[];return m.push({tag:"span",attributes:l,children:[a]}),m}function uo(t){var a=t.content,e=t.extra,n=f(f({},e.attributes),{},{class:e.classes.join(" ")}),r=zt(e.styles);r.length>0&&(n.style=r);var i=[];return i.push({tag:"span",attributes:n,children:[a]}),i}var $t=N.styles;function Zt(t){var a=t[0],e=t[1],n=t.slice(4),r=Pt(n,1),i=r[0],o=null;return Array.isArray(i)?o={tag:"g",attributes:{class:"".concat(v.cssPrefix,"-").concat(Dt.GROUP)},children:[{tag:"path",attributes:{class:"".concat(v.cssPrefix,"-").concat(Dt.SECONDARY),fill:"currentColor",d:i[0]}},{tag:"path",attributes:{class:"".concat(v.cssPrefix,"-").concat(Dt.PRIMARY),fill:"currentColor",d:i[1]}}]}:o={tag:"path",attributes:{fill:"currentColor",d:i}},{found:!0,width:a,height:e,icon:o}}var co={found:!1,width:512,height:512};function mo(t,a){!je&&!v.showMissingIcons&&t&&console.error('Icon with name "'.concat(t,'" and prefix "').concat(a,'" is missing.'))}function ta(t,a){var e=a;return a==="fa"&&v.styleDefault!==null&&(a=X()),new Promise(function(n,r){if(e==="fa"){var i=Ke(t)||{};t=i.iconName||t,a=i.prefix||a}if(t&&a&&$t[a]&&$t[a][t]){var o=$t[a][t];return n(Zt(o))}mo(t,a),n(f(f({},co),{},{icon:v.showMissingIcons&&t?G("missingIconAbstract")||{}:{}}))})}var Da=function(){},aa=v.measurePerformance&&vt&&vt.mark&&vt.measure?vt:{mark:Da,measure:Da},st='FA "7.3.1"',go=function(a){return aa.mark("".concat(st," ").concat(a," begins")),function(){return Ze(a)}},Ze=function(a){aa.mark("".concat(st," ").concat(a," ends")),aa.measure("".concat(st," ").concat(a),"".concat(st," ").concat(a," begins"),"".concat(st," ").concat(a," ends"))},ha={begin:go,end:Ze},wt=function(){};function La(t){var a=t.getAttribute?t.getAttribute(J):null;return typeof a=="string"}function vo(t){var a=t.getAttribute?t.getAttribute(la):null,e=t.getAttribute?t.getAttribute(fa):null;return a&&e}function ho(t){return t&&t.classList&&t.classList.contains&&t.classList.contains(v.replacementClass)}function po(){if(v.autoReplaceSvg===!0)return St.replace;var t=St[v.autoReplaceSvg];return t||St.replace}function bo(t){return A.createElementNS("http://www.w3.org/2000/svg",t)}function yo(t){return A.createElement(t)}function tn(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},e=a.ceFn,n=e===void 0?t.tag==="svg"?bo:yo:e;if(typeof t=="string")return A.createTextNode(t);var r=n(t.tag);Object.keys(t.attributes||[]).forEach(function(o){r.setAttribute(o,t.attributes[o])});var i=t.children||[];return i.forEach(function(o){r.appendChild(tn(o,{ceFn:n}))}),r}function xo(t){var a=" ".concat(t.outerHTML," ");return a="".concat(a,"Font Awesome fontawesome.com "),a}var St={replace:function(a){var e=a[0];if(e.parentNode)if(a[1].forEach(function(r){e.parentNode.insertBefore(tn(r),e)}),e.getAttribute(J)===null&&v.keepOriginalSource){var n=A.createComment(xo(e));e.parentNode.replaceChild(n,e)}else e.remove()},nest:function(a){var e=a[0],n=a[1];if(~ca(e).indexOf(v.replacementClass))return St.replace(a);var r=new RegExp("".concat(v.cssPrefix,"-.*"));if(delete n[0].attributes.id,n[0].attributes.class){var i=n[0].attributes.class.split(" ").reduce(function(s,l){return l===v.replacementClass||l.match(r)?s.toSvg.push(l):s.toNode.push(l),s},{toNode:[],toSvg:[]});n[0].attributes.class=i.toSvg.join(" "),i.toNode.length===0?e.removeAttribute("class"):e.setAttribute("class",i.toNode.join(" "))}var o=n.map(function(s){return mt(s)}).join(`
`);e.setAttribute(J,""),e.innerHTML=o}};function Ma(t){t()}function an(t,a){var e=typeof a=="function"?a:wt;if(t.length===0)e();else{var n=Ma;v.mutateApproach===Si&&(n=Y.requestAnimationFrame||Ma),n(function(){var r=po(),i=ha.begin("mutate");t.map(r),i(),e()})}}var pa=!1;function en(){pa=!0}function ea(){pa=!1}var It=null;function $a(t){if(ka&&v.observeMutations){var a=t.treeCallback,e=a===void 0?wt:a,n=t.nodeCallback,r=n===void 0?wt:n,i=t.pseudoElementsCallback,o=i===void 0?wt:i,s=t.observeMutationsRoot,l=s===void 0?A:s;It=new ka(function(u){if(!pa){var d=X();rt(u).forEach(function(m){if(m.type==="childList"&&m.addedNodes.length>0&&!La(m.addedNodes[0])&&(v.searchPseudoElements&&o(m.target),e(m.target)),m.type==="attributes"&&m.target.parentNode&&v.searchPseudoElements&&o([m.target],!0),m.type==="attributes"&&La(m.target)&&~zi.indexOf(m.attributeName))if(m.attributeName==="class"&&vo(m.target)){var b=Ct(ca(m.target)),p=b.prefix,k=b.iconName;m.target.setAttribute(la,p||d),k&&m.target.setAttribute(fa,k)}else ho(m.target)&&r(m.target)})}}),U&&It.observe(l,{childList:!0,attributes:!0,characterData:!0,subtree:!0})}}function wo(){It&&It.disconnect()}function So(t){var a=t.getAttribute("style"),e=[];return a&&(e=a.split(";").reduce(function(n,r){var i=r.split(":"),o=i[0],s=i.slice(1);return o&&s.length>0&&(n[o]=s.join(":").trim()),n},{})),e}function ko(t){var a=t.getAttribute("data-prefix"),e=t.getAttribute("data-icon"),n=t.innerText!==void 0?t.innerText.trim():"",r=Ct(ca(t));return r.prefix||(r.prefix=X()),a&&e&&(r.prefix=a,r.iconName=e),r.iconName&&r.prefix||(r.prefix&&n.length>0&&(r.iconName=Xi(r.prefix,t.innerText)||ga(r.prefix,Be(t.innerText))),!r.iconName&&v.autoFetchSvg&&t.firstChild&&t.firstChild.nodeType===Node.TEXT_NODE&&(r.iconName=t.firstChild.data)),r}function Ao(t){var a=rt(t.attributes).reduce(function(e,n){return e.name!=="class"&&e.name!=="style"&&(e[n.name]=n.value),e},{});return a}function Io(){return{iconName:null,prefix:null,transform:$,symbol:!1,mask:{iconName:null,prefix:null,rest:[]},maskId:null,extra:{classes:[],styles:{},attributes:{}}}}function Ra(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{styleParser:!0},e=ko(t),n=e.iconName,r=e.prefix,i=e.rest,o=Ao(t),s=Jt("parseNodeAttributes",{},t),l=a.styleParser?So(t):[];return f({iconName:n,prefix:r,transform:$,mask:{iconName:null,prefix:null,rest:[]},maskId:null,symbol:!1,extra:{classes:i,styles:l,attributes:o}},s)}var Fo=N.styles;function nn(t){var a=v.autoReplaceSvg==="nest"?Ra(t,{styleParser:!1}):Ra(t);return~a.extra.classes.indexOf(De)?G("generateLayersText",t,a):G("generateSvgReplacementMutation",t,a)}function Po(){return[].concat(D(ze),D(_e))}function Wa(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:null;if(!U)return Promise.resolve();var e=A.documentElement.classList,n=function(m){return e.add("".concat(Fa,"-").concat(m))},r=function(m){return e.remove("".concat(Fa,"-").concat(m))},i=v.autoFetchSvg?Po():re.concat(Object.keys(Fo));i.includes("fa")||i.push("fa");var o=[".".concat(De,":not([").concat(J,"])")].concat(i.map(function(d){return".".concat(d,":not([").concat(J,"])")})).join(", ");if(o.length===0)return Promise.resolve();var s=[];try{s=rt(t.querySelectorAll(o))}catch{}if(s.length>0)n("pending"),r("complete");else return Promise.resolve();var l=ha.begin("onTree"),u=s.reduce(function(d,m){try{var b=nn(m);b&&d.push(b)}catch(p){je||p.name==="MissingIcon"&&console.error(p)}return d},[]);return new Promise(function(d,m){Promise.all(u).then(function(b){an(b,function(){n("active"),n("complete"),r("pending"),typeof a=="function"&&a(),l(),d()})}).catch(function(b){l(),m(b)})})}function zo(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:null;nn(t).then(function(e){e&&an([e],a)})}function _o(t){return function(a){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},n=(a||{}).icon?a:Qt(a||{}),r=e.mask;return r&&(r=(r||{}).icon?r:Qt(r||{})),t(n,f(f({},e),{},{mask:r}))}}var Co=function(a){var e=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},n=e.transform,r=n===void 0?$:n,i=e.symbol,o=i===void 0?!1:i,s=e.mask,l=s===void 0?null:s,u=e.maskId,d=u===void 0?null:u,m=e.classes,b=m===void 0?[]:m,p=e.attributes,k=p===void 0?{}:p,y=e.styles,P=y===void 0?{}:y;if(a){var g=a.prefix,c=a.iconName,x=a.icon;return Ot(f({type:"icon"},a),function(){return Q("beforeDOMElementCreation",{iconDefinition:a,params:e}),va({icons:{main:Zt(x),mask:l?Zt(l.icon):{found:!1,width:null,height:null,icon:{}}},prefix:g,iconName:c,transform:f(f({},$),r),symbol:o,maskId:d,extra:{attributes:k,styles:P,classes:b}})})}},Oo={mixout:function(){return{icon:_o(Co)}},hooks:function(){return{mutationObserverCallbacks:function(e){return e.treeCallback=Wa,e.nodeCallback=zo,e}}},provides:function(a){a.i2svg=function(e){var n=e.node,r=n===void 0?A:n,i=e.callback,o=i===void 0?function(){}:i;return Wa(r,o)},a.generateSvgReplacementMutation=function(e,n){var r=n.iconName,i=n.prefix,o=n.transform,s=n.symbol,l=n.mask,u=n.maskId,d=n.extra;return new Promise(function(m,b){Promise.all([ta(r,i),l.iconName?ta(l.iconName,l.prefix):Promise.resolve({found:!1,width:512,height:512,icon:{}})]).then(function(p){var k=Pt(p,2),y=k[0],P=k[1];m([e,va({icons:{main:y,mask:P},prefix:i,iconName:r,transform:o,symbol:s,maskId:u,extra:d,watchable:!0})])}).catch(b)})},a.generateAbstractIcon=function(e){var n=e.children,r=e.attributes,i=e.main,o=e.transform,s=e.styles,l=zt(s);l.length>0&&(r.style=l);var u;return da(o)&&(u=G("generateAbstractTransformGrouping",{main:i,transform:o,containerWidth:i.width,iconWidth:i.width})),n.push(u||i.icon),{children:n,attributes:r}}}},Eo={mixout:function(){return{layer:function(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},r=n.classes,i=r===void 0?[]:r;return Ot({type:"layer"},function(){Q("beforeDOMElementCreation",{assembler:e,params:n});var o=[];return e(function(s){Array.isArray(s)?s.map(function(l){o=o.concat(l.abstract)}):o=o.concat(s.abstract)}),[{tag:"span",attributes:{class:["".concat(v.cssPrefix,"-layers")].concat(D(i)).join(" ")},children:o}]})}}}},To={mixout:function(){return{counter:function(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{};n.title;var r=n.classes,i=r===void 0?[]:r,o=n.attributes,s=o===void 0?{}:o,l=n.styles,u=l===void 0?{}:l;return Ot({type:"counter",content:e},function(){return Q("beforeDOMElementCreation",{content:e,params:n}),uo({content:e.toString(),extra:{attributes:s,styles:u,classes:["".concat(v.cssPrefix,"-layers-counter")].concat(D(i))}})})}}}},jo={mixout:function(){return{text:function(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},r=n.transform,i=r===void 0?$:r,o=n.classes,s=o===void 0?[]:o,l=n.attributes,u=l===void 0?{}:l,d=n.styles,m=d===void 0?{}:d;return Ot({type:"text",content:e},function(){return Q("beforeDOMElementCreation",{content:e,params:n}),Na({content:e,transform:f(f({},$),i),extra:{attributes:u,styles:m,classes:["".concat(v.cssPrefix,"-layers-text")].concat(D(s))}})})}}},provides:function(a){a.generateLayersText=function(e,n){var r=n.transform,i=n.extra,o=null,s=null;if(ee){var l=parseInt(getComputedStyle(e).fontSize,10),u=e.getBoundingClientRect();o=u.width/l,s=u.height/l}return Promise.resolve([e,Na({content:e.innerHTML,width:o,height:s,transform:r,extra:i,watchable:!0})])}}},rn=new RegExp('"',"ug"),Ba=[1105920,1112319],Ua=f(f(f(f({},{FontAwesome:{normal:"fas",400:"fas"}}),nr),xi),dr),na=Object.keys(Ua).reduce(function(t,a){return t[a.toLowerCase()]=Ua[a],t},{}),No=Object.keys(na).reduce(function(t,a){var e=na[a];return t[a]=e[900]||D(Object.entries(e))[0][1],t},{});function Do(t){var a=t.replace(rn,"");return Be(D(a)[0]||"")}function Lo(t){var a=t.getPropertyValue("font-feature-settings").includes("ss01"),e=t.getPropertyValue("content"),n=e.replace(rn,""),r=n.codePointAt(0),i=r>=Ba[0]&&r<=Ba[1],o=n.length===2?n[0]===n[1]:!1;return i||o||a}function Mo(t,a){var e=t.replace(/^['"]|['"]$/g,"").toLowerCase(),n=parseInt(a),r=isNaN(n)?"normal":n;return(na[e]||{})[r]||No[e]}function Ha(t,a){var e="".concat(wi).concat(a.replace(":","-"));return new Promise(function(n,r){if(t.getAttribute(e)!==null)return n();var i=rt(t.children),o=i.filter(function(L){return L.getAttribute(Xt)===a})[0],s=Y.getComputedStyle(t,a),l=s.getPropertyValue("font-family"),u=l.match(Fi),d=s.getPropertyValue("font-weight"),m=s.getPropertyValue("content");if(o&&!u)return t.removeChild(o),n();if(u&&m!=="none"&&m!==""){var b=s.getPropertyValue("content"),p=Mo(l,d),k=Do(b),y=u[0].startsWith("FontAwesome"),P=Lo(s),g=ga(p,k),c=g;if(y){var x=Gi(k);x.iconName&&x.prefix&&(g=x.iconName,p=x.prefix)}if(g&&!P&&(!o||o.getAttribute(la)!==p||o.getAttribute(fa)!==c)){t.setAttribute(e,c),o&&t.removeChild(o);var S=Io(),z=S.extra;z.attributes[Xt]=a,ta(g,p).then(function(L){var it=va(f(f({},S),{},{icons:{main:L,mask:Je()},prefix:p,iconName:c,extra:z,watchable:!0})),Et=A.createElementNS("http://www.w3.org/2000/svg","svg");a==="::before"?t.insertBefore(Et,t.firstChild):t.appendChild(Et),Et.outerHTML=it.map(function(un){return mt(un)}).join(`
`),t.removeAttribute(e),n()}).catch(r)}else n()}else n()})}function $o(t){return Promise.all([Ha(t,"::before"),Ha(t,"::after")])}function Ro(t){return t.parentNode!==document.head&&!~ki.indexOf(t.tagName.toUpperCase())&&!t.getAttribute(Xt)&&(!t.parentNode||t.parentNode.tagName!=="svg")}var Wo=function(a){return!!a&&Te.some(function(e){return a.includes(e)})},Bo=function(a){if(!a)return[];var e=new Set,n=a.split(/,(?![^()]*\))/).map(function(l){return l.trim()});n=n.flatMap(function(l){return l.includes("(")?l:l.split(",").map(function(u){return u.trim()})});var r=xt(n),i;try{for(r.s();!(i=r.n()).done;){var o=i.value;if(Wo(o)){var s=Te.reduce(function(l,u){return l.replace(u,"")},o);s!==""&&s!=="*"&&e.add(s)}}}catch(l){r.e(l)}finally{r.f()}return e};function Ya(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1;if(U){var e;if(a)e=t;else if(v.searchPseudoElementsFullScan)e=t.querySelectorAll("*");else{var n=new Set,r=xt(document.styleSheets),i;try{for(r.s();!(i=r.n()).done;){var o=i.value;try{var s=xt(o.cssRules),l;try{for(s.s();!(l=s.n()).done;){var u=l.value,d=Bo(u.selectorText),m=xt(d),b;try{for(m.s();!(b=m.n()).done;){var p=b.value;n.add(p)}}catch(y){m.e(y)}finally{m.f()}}}catch(y){s.e(y)}finally{s.f()}}catch(y){v.searchPseudoElementsWarnings&&console.warn("Font Awesome: cannot parse stylesheet: ".concat(o.href," (").concat(y.message,`)
If it declares any Font Awesome CSS pseudo-elements, they will not be rendered as SVG icons. Add crossorigin="anonymous" to the <link>, enable searchPseudoElementsFullScan for slower but more thorough DOM parsing, or suppress this warning by setting searchPseudoElementsWarnings to false.`))}}}catch(y){r.e(y)}finally{r.f()}if(!n.size)return;var k=Array.from(n).join(", ");try{e=t.querySelectorAll(k)}catch{}}return new Promise(function(y,P){var g=rt(e).filter(Ro).map($o),c=ha.begin("searchPseudoElements");en(),Promise.all(g).then(function(){c(),ea(),y()}).catch(function(){c(),ea(),P()})})}}var Uo={hooks:function(){return{mutationObserverCallbacks:function(e){return e.pseudoElementsCallback=Ya,e}}},provides:function(a){a.pseudoElements2svg=function(e){var n=e.node,r=n===void 0?A:n;v.searchPseudoElements&&Ya(r)}}},Xa=!1,Ho={mixout:function(){return{dom:{unwatch:function(){en(),Xa=!0}}}},hooks:function(){return{bootstrap:function(){$a(Jt("mutationObserverCallbacks",{}))},noAuto:function(){wo()},watch:function(e){var n=e.observeMutationsRoot;Xa?ea():$a(Jt("mutationObserverCallbacks",{observeMutationsRoot:n}))}}}},Ga=function(a){var e={size:16,x:0,y:0,flipX:!1,flipY:!1,rotate:0};return a.toLowerCase().split(" ").reduce(function(n,r){var i=r.toLowerCase().split("-"),o=i[0],s=i.slice(1).join("-");if(o&&s==="h")return n.flipX=!0,n;if(o&&s==="v")return n.flipY=!0,n;if(s=parseFloat(s),isNaN(s))return n;switch(o){case"grow":n.size=n.size+s;break;case"shrink":n.size=n.size-s;break;case"left":n.x=n.x-s;break;case"right":n.x=n.x+s;break;case"up":n.y=n.y-s;break;case"down":n.y=n.y+s;break;case"rotate":n.rotate=n.rotate+s;break}return n},e)},Yo={mixout:function(){return{parse:{transform:function(e){return Ga(e)}}}},hooks:function(){return{parseNodeAttributes:function(e,n){var r=n.getAttribute("data-fa-transform");return r&&(e.transform=Ga(r)),e}}},provides:function(a){a.generateAbstractTransformGrouping=function(e){var n=e.main,r=e.transform,i=e.containerWidth,o=e.iconWidth,s={transform:"translate(".concat(i/2," 256)")},l="translate(".concat(r.x*32,", ").concat(r.y*32,") "),u="scale(".concat(r.size/16*(r.flipX?-1:1),", ").concat(r.size/16*(r.flipY?-1:1),") "),d="rotate(".concat(r.rotate," 0 0)"),m={transform:"".concat(l," ").concat(u," ").concat(d)},b={transform:"translate(".concat(o/2*-1," -256)")},p={outer:s,inner:m,path:b};return{tag:"g",attributes:f({},p.outer),children:[{tag:"g",attributes:f({},p.inner),children:[{tag:n.icon.tag,children:n.icon.children,attributes:f(f({},n.icon.attributes),p.path)}]}]}}}},Rt={x:0,y:0,width:"100%",height:"100%"};function Va(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!0;return t.attributes&&(t.attributes.fill||a)&&(t.attributes.fill="black"),t}function Xo(t){return t.tag==="g"?t.children:[t]}var Go={hooks:function(){return{parseNodeAttributes:function(e,n){var r=n.getAttribute("data-fa-mask"),i=r?Ct(r.split(" ").map(function(o){return o.trim()})):Je();return i.prefix||(i.prefix=X()),e.mask=i,e.maskId=n.getAttribute("data-fa-mask-id"),e}}},provides:function(a){a.generateAbstractMask=function(e){var n=e.children,r=e.attributes,i=e.main,o=e.mask,s=e.maskId,l=e.transform,u=i.width,d=i.icon,m=o.width,b=o.icon,p=Li({transform:l,containerWidth:m,iconWidth:u}),k={tag:"rect",attributes:f(f({},Rt),{},{fill:"white"})},y=d.children?{children:d.children.map(Va)}:{},P={tag:"g",attributes:f({},p.inner),children:[Va(f({tag:d.tag,attributes:f(f({},d.attributes),p.path)},y))]},g={tag:"g",attributes:f({},p.outer),children:[P]},c="mask-".concat(s||za()),x="clip-".concat(s||za()),S={tag:"mask",attributes:f(f({},Rt),{},{id:c,maskUnits:"userSpaceOnUse",maskContentUnits:"userSpaceOnUse"}),children:[k,g]},z={tag:"defs",children:[{tag:"clipPath",attributes:{id:x},children:Xo(b)},S]};return n.push(z,{tag:"rect",attributes:f({fill:"currentColor","clip-path":"url(#".concat(x,")"),mask:"url(#".concat(c,")")},Rt)}),{children:n,attributes:r}}}},Vo={provides:function(a){var e=!1;Y.matchMedia&&(e=Y.matchMedia("(prefers-reduced-motion: reduce)").matches),a.missingIconAbstract=function(){var n=[],r={fill:"currentColor"},i={attributeType:"XML",repeatCount:"indefinite",dur:"2s"};n.push({tag:"path",attributes:f(f({},r),{},{d:"M156.5,447.7l-12.6,29.5c-18.7-9.5-35.9-21.2-51.5-34.9l22.7-22.7C127.6,430.5,141.5,440,156.5,447.7z M40.6,272H8.5 c1.4,21.2,5.4,41.7,11.7,61.1L50,321.2C45.1,305.5,41.8,289,40.6,272z M40.6,240c1.4-18.8,5.2-37,11.1-54.1l-29.5-12.6 C14.7,194.3,10,216.7,8.5,240H40.6z M64.3,156.5c7.8-14.9,17.2-28.8,28.1-41.5L69.7,92.3c-13.7,15.6-25.5,32.8-34.9,51.5 L64.3,156.5z M397,419.6c-13.9,12-29.4,22.3-46.1,30.4l11.9,29.8c20.7-9.9,39.8-22.6,56.9-37.6L397,419.6z M115,92.4 c13.9-12,29.4-22.3,46.1-30.4l-11.9-29.8c-20.7,9.9-39.8,22.6-56.8,37.6L115,92.4z M447.7,355.5c-7.8,14.9-17.2,28.8-28.1,41.5 l22.7,22.7c13.7-15.6,25.5-32.9,34.9-51.5L447.7,355.5z M471.4,272c-1.4,18.8-5.2,37-11.1,54.1l29.5,12.6 c7.5-21.1,12.2-43.5,13.6-66.8H471.4z M321.2,462c-15.7,5-32.2,8.2-49.2,9.4v32.1c21.2-1.4,41.7-5.4,61.1-11.7L321.2,462z M240,471.4c-18.8-1.4-37-5.2-54.1-11.1l-12.6,29.5c21.1,7.5,43.5,12.2,66.8,13.6V471.4z M462,190.8c5,15.7,8.2,32.2,9.4,49.2h32.1 c-1.4-21.2-5.4-41.7-11.7-61.1L462,190.8z M92.4,397c-12-13.9-22.3-29.4-30.4-46.1l-29.8,11.9c9.9,20.7,22.6,39.8,37.6,56.9 L92.4,397z M272,40.6c18.8,1.4,36.9,5.2,54.1,11.1l12.6-29.5C317.7,14.7,295.3,10,272,8.5V40.6z M190.8,50 c15.7-5,32.2-8.2,49.2-9.4V8.5c-21.2,1.4-41.7,5.4-61.1,11.7L190.8,50z M442.3,92.3L419.6,115c12,13.9,22.3,29.4,30.5,46.1 l29.8-11.9C470,128.5,457.3,109.4,442.3,92.3z M397,92.4l22.7-22.7c-15.6-13.7-32.8-25.5-51.5-34.9l-12.6,29.5 C370.4,72.1,384.4,81.5,397,92.4z"})});var o=f(f({},i),{},{attributeName:"opacity"}),s={tag:"circle",attributes:f(f({},r),{},{cx:"256",cy:"364",r:"28"}),children:[]};return e||s.children.push({tag:"animate",attributes:f(f({},i),{},{attributeName:"r",values:"28;14;28;28;14;28;"})},{tag:"animate",attributes:f(f({},o),{},{values:"1;0;1;1;0;1;"})}),n.push(s),n.push({tag:"path",attributes:f(f({},r),{},{opacity:"1",d:"M263.7,312h-16c-6.6,0-12-5.4-12-12c0-71,77.4-63.9,77.4-107.8c0-20-17.8-40.2-57.4-40.2c-29.1,0-44.3,9.6-59.2,28.7 c-3.9,5-11.1,6-16.2,2.4l-13.1-9.2c-5.6-3.9-6.9-11.8-2.6-17.2c21.2-27.2,46.4-44.7,91.2-44.7c52.3,0,97.4,29.8,97.4,80.2 c0,67.6-77.4,63.5-77.4,107.8C275.7,306.6,270.3,312,263.7,312z"}),children:e?[]:[{tag:"animate",attributes:f(f({},o),{},{values:"1;0;0;0;0;1;"})}]}),e||n.push({tag:"path",attributes:f(f({},r),{},{opacity:"0",d:"M232.5,134.5l7,168c0.3,6.4,5.6,11.5,12,11.5h9c6.4,0,11.7-5.1,12-11.5l7-168c0.3-6.8-5.2-12.5-12-12.5h-23 C237.7,122,232.2,127.7,232.5,134.5z"}),children:[{tag:"animate",attributes:f(f({},o),{},{values:"0;0;1;1;0;0;"})}]}),{tag:"g",attributes:{class:"missing"},children:n}}}},qo={hooks:function(){return{parseNodeAttributes:function(e,n){var r=n.getAttribute("data-fa-symbol"),i=r===null?!1:r===""?!0:r;return e.symbol=i,e}}}},Ko=[Ri,Oo,Eo,To,jo,Uo,Ho,Yo,Go,Vo,qo];eo(Ko,{mixoutsTo:T});T.noAuto;T.config;var Jo=T.library;T.dom;var ra=T.parse;T.findIconDefinition;T.toHtml;var Qo=T.icon;T.layer;T.text;T.counter;/*!
 * Font Awesome Free 7.3.1 by @fontawesome - https://fontawesome.com
 * License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License)
 * Copyright 2026 Fonticons, Inc.
 */var Zo={prefix:"fas",iconName:"xmark",icon:[384,512,[128473,10005,10006,10060,215,"close","multiply","remove","times"],"f00d","M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"]},ts=Zo;function ia(t,a){(a==null||a>t.length)&&(a=t.length);for(var e=0,n=Array(a);e<a;e++)n[e]=t[e];return n}function as(t){if(Array.isArray(t))return ia(t)}function w(t,a,e){return(a=ss(a))in t?Object.defineProperty(t,a,{value:e,enumerable:!0,configurable:!0,writable:!0}):t[a]=e,t}function es(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function ns(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function qa(t,a){var e=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);a&&(n=n.filter(function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable})),e.push.apply(e,n)}return e}function F(t){for(var a=1;a<arguments.length;a++){var e=arguments[a]!=null?arguments[a]:{};a%2?qa(Object(e),!0).forEach(function(n){w(t,n,e[n])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(e)):qa(Object(e)).forEach(function(n){Object.defineProperty(t,n,Object.getOwnPropertyDescriptor(e,n))})}return t}function Wt(t,a){if(t==null)return{};var e,n,r=rs(t,a);if(Object.getOwnPropertySymbols){var i=Object.getOwnPropertySymbols(t);for(n=0;n<i.length;n++)e=i[n],a.indexOf(e)===-1&&{}.propertyIsEnumerable.call(t,e)&&(r[e]=t[e])}return r}function rs(t,a){if(t==null)return{};var e={};for(var n in t)if({}.hasOwnProperty.call(t,n)){if(a.indexOf(n)!==-1)continue;e[n]=t[n]}return e}function is(t){return as(t)||es(t)||ls(t)||ns()}function os(t,a){if(typeof t!="object"||!t)return t;var e=t[Symbol.toPrimitive];if(e!==void 0){var n=e.call(t,a);if(typeof n!="object")return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return(a==="string"?String:Number)(t)}function ss(t){var a=os(t,"string");return typeof a=="symbol"?a:a+""}function Ft(t){"@babel/helpers - typeof";return Ft=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(a){return typeof a}:function(a){return a&&typeof Symbol=="function"&&a.constructor===Symbol&&a!==Symbol.prototype?"symbol":typeof a},Ft(t)}function ls(t,a){if(t){if(typeof t=="string")return ia(t,a);var e={}.toString.call(t).slice(8,-1);return e==="Object"&&t.constructor&&(e=t.constructor.name),e==="Map"||e==="Set"?Array.from(t):e==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(e)?ia(t,a):void 0}}function Bt(t,a){return Array.isArray(a)&&a.length>0||!Array.isArray(a)&&a?w({},t,a):{}}function fs(t){var a,e=(a={"fa-spin":t.spin,"fa-pulse":t.pulse,"fa-fw":t.fixedWidth,"fa-border":t.border,"fa-li":t.listItem,"fa-inverse":t.inverse,"fa-flip":t.flip===!0,"fa-flip-horizontal":t.flip==="horizontal"||t.flip==="both","fa-flip-vertical":t.flip==="vertical"||t.flip==="both"},w(w(w(w(w(w(w(w(w(w(a,"fa-".concat(t.size),t.size!==null),"fa-rotate-".concat(t.rotation),t.rotation!==null),"fa-rotate-by",t.rotateBy),"fa-pull-".concat(t.pull),t.pull!==null),"fa-swap-opacity",t.swapOpacity),"fa-bounce",t.bounce),"fa-shake",t.shake),"fa-beat",t.beat),"fa-fade",t.fade),"fa-beat-fade",t.beatFade),w(w(w(w(w(w(w(w(w(w(a,"fa-flash",t.flash),"fa-spin-pulse",t.spinPulse),"fa-spin-reverse",t.spinReverse),"fa-width-auto",t.widthAuto),"fa-canvas-square",t.canvasSquare),"fa-canvas-roomy",t.canvasRoomy),"fa-flip-360",t.flip360),"fa-buzz",t.buzz),"fa-float",t.float),"fa-jello",t.jello),w(w(w(w(w(a,"fa-spin-snap",t.spinSnap),"fa-spin-snap-4",t.spinSnap4),"fa-spin-snap-8",t.spinSnap8),"fa-swing",t.swing),"fa-wag",t.wag));return Object.keys(e).map(function(n){return e[n]?n:null}).filter(function(n){return n})}var us=typeof globalThis<"u"?globalThis:typeof window<"u"?window:typeof global<"u"?global:typeof self<"u"?self:{},on={exports:{}};(function(t){(function(a){var e=function(g,c,x){if(!u(c)||m(c)||b(c)||p(c)||l(c))return c;var S,z=0,L=0;if(d(c))for(S=[],L=c.length;z<L;z++)S.push(e(g,c[z],x));else{S={};for(var it in c)Object.prototype.hasOwnProperty.call(c,it)&&(S[g(it,x)]=e(g,c[it],x))}return S},n=function(g,c){c=c||{};var x=c.separator||"_",S=c.split||/(?=[A-Z])/;return g.split(S).join(x)},r=function(g){return k(g)?g:(g=g.replace(/[\-_\s]+(.)?/g,function(c,x){return x?x.toUpperCase():""}),g.substr(0,1).toLowerCase()+g.substr(1))},i=function(g){var c=r(g);return c.substr(0,1).toUpperCase()+c.substr(1)},o=function(g,c){return n(g,c).toLowerCase()},s=Object.prototype.toString,l=function(g){return typeof g=="function"},u=function(g){return g===Object(g)},d=function(g){return s.call(g)=="[object Array]"},m=function(g){return s.call(g)=="[object Date]"},b=function(g){return s.call(g)=="[object RegExp]"},p=function(g){return s.call(g)=="[object Boolean]"},k=function(g){return g=g-0,g===g},y=function(g,c){var x=c&&"process"in c?c.process:c;return typeof x!="function"?g:function(S,z){return x(S,g,z)}},P={camelize:r,decamelize:o,pascalize:i,depascalize:o,camelizeKeys:function(g,c){return e(y(r,c),g)},decamelizeKeys:function(g,c){return e(y(o,c),g,c)},pascalizeKeys:function(g,c){return e(y(i,c),g)},depascalizeKeys:function(){return this.decamelizeKeys.apply(this,arguments)}};t.exports?t.exports=P:a.humps=P})(us)})(on);var cs=on.exports,ds=["gradientFill"],ms=["class","style"],gs=["type","stops","id"];function vs(t){return t.split(";").map(function(a){return a.trim()}).filter(function(a){return a}).reduce(function(a,e){var n=e.indexOf(":"),r=cs.camelize(e.slice(0,n)),i=e.slice(n+1).trim();return a[r]=i,a},{})}function hs(t){return t.split(/\s+/).reduce(function(a,e){return a[e]=!0,a},{})}function ps(t,a){return yt("stop",F({key:"".concat(a,"-").concat(t.offset),offset:t.offset,"stop-color":t.color},t.opacity!==void 0&&{"stop-opacity":t.opacity}))}function sn(t){if(typeof t=="string")return t;var a=(t.children||[]).map(sn);return t.tag==="path"&&t.attributes&&"fill"in t.attributes?F(F({},t),{},{attributes:F(F({},t.attributes),{},{fill:void 0}),children:a}):F(F({},t),{},{children:a})}function ln(t){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:{},e=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{};if(typeof t=="string")return t;var n=a.gradientFill,r=n===void 0?null:n,i=Wt(a,ds),o=!!r||"fill"in e,s=o?sn(t):t,l=(s.children||[]).map(function(S){return ln(S,{},{})}),u=Object.keys(s.attributes||{}).reduce(function(S,z){var L=s.attributes[z];switch(z){case"class":S.class=hs(L);break;case"style":S.style=vs(L);break;default:S.attrs[z]=L}return S},{attrs:{},class:{},style:{}});e.class;var d=e.style,m=d===void 0?{}:d,b=Wt(e,ms);if(r&&r.id&&(r.type==="linear"||r.type==="radial")){var p=r.type,k=r.stops,y=k===void 0?[]:k,P=r.id,g=Wt(r,gs),c=p==="linear"?"linearGradient":"radialGradient",x=yt(c,F(F({},g),{},{id:P}),y.map(ps));return yt(s.tag,F(F(F(F({},i),{},{class:u.class,style:F(F({},u.style),m)},u.attrs),b),{},{fill:"url(#".concat(P,")")}),[x].concat(is(l)))}return yt(t.tag,F(F(F({},i),{},{class:u.class,style:F(F({},u.style),m)},u.attrs),b),l)}var fn=!1;try{fn=!0}catch{}function Ka(){if(!fn&&console&&typeof console.error=="function"){var t;(t=console).error.apply(t,arguments)}}function Ja(t){if(t&&Ft(t)==="object"&&t.prefix&&t.iconName&&t.icon)return t;if(ra.icon)return ra.icon(t);if(t===null)return null;if(Ft(t)==="object"&&t.prefix&&t.iconName)return t;if(Array.isArray(t)&&t.length===2)return{prefix:t[0],iconName:t[1]};if(typeof t=="string")return{prefix:"fas",iconName:t}}var bs=cn({name:"FontAwesomeIcon",props:{border:{type:Boolean,default:!1},fixedWidth:{type:Boolean,default:!1},flip:{type:[Boolean,String],default:!1,validator:function(a){return[!0,!1,"horizontal","vertical","both"].indexOf(a)>-1}},icon:{type:[Object,Array,String],required:!0},mask:{type:[Object,Array,String],default:null},maskId:{type:String,default:null},listItem:{type:Boolean,default:!1},pull:{type:String,default:null,validator:function(a){return["right","left"].indexOf(a)>-1}},pulse:{type:Boolean,default:!1},rotation:{type:[String,Number],default:null,validator:function(a){return[90,180,270].indexOf(Number.parseInt(a,10))>-1}},rotateBy:{type:Boolean,default:!1},swapOpacity:{type:Boolean,default:!1},size:{type:String,default:null,validator:function(a){return["2xs","xs","sm","lg","xl","2xl","1x","2x","3x","4x","5x","6x","7x","8x","9x","10x"].indexOf(a)>-1}},spin:{type:Boolean,default:!1},transform:{type:[String,Object],default:null},symbol:{type:[Boolean,String],default:!1},title:{type:String,default:null},titleId:{type:String,default:null},inverse:{type:Boolean,default:!1},bounce:{type:Boolean,default:!1},shake:{type:Boolean,default:!1},beat:{type:Boolean,default:!1},fade:{type:Boolean,default:!1},beatFade:{type:Boolean,default:!1},flash:{type:Boolean,default:!1},spinPulse:{type:Boolean,default:!1},spinReverse:{type:Boolean,default:!1},widthAuto:{type:Boolean,default:!1},canvasSquare:{type:Boolean,default:!1},canvasRoomy:{type:Boolean,default:!1},gradientFill:{type:Object,default:null,validator:function(a){return typeof a.id!="string"||!a.id?(console.warn("FontAwesomeIcon: gradientFill.id must be a non-empty string"),!1):a.type!=="linear"&&a.type!=="radial"?(console.warn('FontAwesomeIcon: gradientFill.type must be "linear" or "radial"'),!1):!0}},flip360:{type:Boolean,default:!1},buzz:{type:Boolean,default:!1},float:{type:Boolean,default:!1},jello:{type:Boolean,default:!1},spinSnap:{type:Boolean,default:!1},spinSnap4:{type:Boolean,default:!1},spinSnap8:{type:Boolean,default:!1},swing:{type:Boolean,default:!1},wag:{type:Boolean,default:!1}},setup:function(a,e){var n=e.attrs,r=Z(function(){return Ja(a.icon)}),i=Z(function(){return Bt("classes",fs(a))}),o=Z(function(){return Bt("transform",typeof a.transform=="string"?ra.transform(a.transform):a.transform)}),s=Z(function(){return Bt("mask",Ja(a.mask))}),l=Z(function(){var d=F(F(F(F({},i.value),o.value),s.value),{},{symbol:a.symbol,maskId:a.maskId});return d.title=a.title,d.titleId=a.titleId,Qo(r.value,d)});dn(l,function(d){if(!d)return Ka("Could not find one or more icon(s)",r.value,s.value)},{immediate:!0}),a.gradientFill&&a.symbol&&Ka("gradientFill is not supported when symbol is true and will be ignored");var u=Z(function(){return l.value?ln(l.value.abstract[0],{gradientFill:a.symbol?null:a.gradientFill},n):null});return function(){return u.value}}});const q="http://127.0.0.1:8000";Jo.add(ts);const ys={components:{popup:pn,FontAwesomeIcon:bs},props:{idEdit:{type:Number,required:!0},idCategory:{type:Number,required:!0},treeData:Object},data(){return{titleauk:"",aukstructures:[],filterByCategoryAukstructures:[],categories:{},link:"",firstId:"",contentStyleObj:{height:""},activeId:this.firstId,curAuk:this.item,showItems:!0,showSearch:!1,isFavorite:!1,loaded:!1,loading:!1,searchTerm:"",matchingFiles:[],aircraftTitle:"",path:"",aircraft:"",error:"",isLoading:!1,alert:!1,alertType:"",overlay:!1,snackbarText:"",favorites:[],highlighted:[],currentHighlight:0,iframe:null,IframeisLoaded:!1}},mounted(){this.getFavorites(),this.aircrafts||this.$store.dispatch("Course/fetchAircrafts"),this.$store.dispatch("Course/fetchCourse",this.idEdit),this.$store.dispatch("Course/fetchCategory",this.idCategory),this.$store.dispatch("Course/fetchCategories"),this.$store.dispatch("Course/fetchAircrafts"),this.$store.dispatch("Course/fetchAircraft",this.aircraft),V.get(q+"/api/course?course_id="+this.idEdit+"&&category_id="+this.idCategory).then(t=>{this.titleauk=t.data[0].title,this.aukstructures=t.data[0].aukstructures,this.filterByCategoryAukstructures=this.aukstructures.filter(a=>a.categories?a.categories.includes(this.categoryCode.toString().trim()):!0).sort((a,e)=>a.id-e.id),this.path=t.data[0].path,this.aircraft=t.data[0].aircraft_id}).catch(t=>{console.error(t)})},watch:{link(t,a){},activeId(t,a){},getFirstAukId:function(t,a){t&&this.getlink(t)}},computed:{...vn("Course",["course","category","totalCourses","aircrafts","aircraft"]),...gn("Course",["categories","courses"]),idEditComputed(){return this.idEdit},idCategoryComputed(){return this.idCategory},categoryCode(){return this.category?this.category.code:null},getFirstAukId(){const t=this.aukstructures.find(a=>a.type===3);return t&&this.getlink(t.id),t?t.id:null}},methods:{replaceNodeContent(t,a){const e=document.createElement("div");e.innerHTML=a;const n=e.firstChild,r=t.attributes;for(let i=r.length-1;i>=0;i--){const o=r.item(i).nodeName,s=r.item(i).nodeValue,l=JSON.parse('"'+s+'"');n.setAttribute(o,l)}t.parentNode.replaceChild(n,t)},async getlink(t){this.isLoading=!0,this.activeId=t;try{const a=await V.get(q+"/api/getlink/"+t);this.link=a.data}catch(a){console.log(a)}finally{this.isLoading=!1}},loadContent(t,a){this.isLoading=!0,this.getlink(t),setTimeout(()=>{const n=this.$refs.myIframe.contentDocument,r=new DOMParser().parseFromString(n.body.innerHTML,"text/html");try{a.forEach(i=>{const o=r.evaluate(i.originalXpath,r,null,XPathResult.FIRST_ORDERED_NODE_TYPE,null).singleNodeValue;if(o){const s=o.parentNode;s.innerHTML=i.highlightedText}})}catch(i){console.log(i)}n.body.innerHTML=r.documentElement.innerHTML,this.highlightNodes(n)},1e3),this.isLoading=!1},highlightNodes(t){this.highlighted=Array.from(t.querySelectorAll(".highlighted")),this.currentHighlight=0,this.scrollToHighlight(this.highlighted[this.currentHighlight])},scrollToHighlight(t){const a=this.$refs.myIframe,e=a.contentWindow.document,n=a.getBoundingClientRect(),i=t.getBoundingClientRect().top-n.top+e.documentElement.scrollTop;e.documentElement.scrollTop=i},scrollToNext(){this.highlighted.length>0?(this.currentHighlight=(this.currentHighlight+1)%this.highlighted.length,this.scrollToHighlight(this.highlighted[this.currentHighlight])):this.currentHighlight=0},scrollToPrev(){this.highlighted.length>0?(this.currentHighlight=(this.currentHighlight-1+this.highlighted.length)%this.highlighted.length,this.scrollToHighlight(this.highlighted[this.currentHighlight])):this.currentHighlight=0},showthumb(t){document.getElementById(t).style.border="2px doted grey ",document.getElementById(t).style.borderRadius="4px",t!==this.activeId&&(document.getElementById(t).style.background="#D3D3D3"),document.getElementById(t).style.transform="scale(1.03)"},hidethumb(t){document.getElementById(t).style.border="none",t!==this.activeId&&(document.getElementById(t).style.background="none"),document.getElementById(t).style.transform="scale(1.0)"},getfirstauk:function(t){V.get(q+"/api/getfirstauk/"+t).then(a=>{this.firstId=a.data,this.getlink(this.firstId)})},toggleFavorite(){this.isFavorite=!this.isFavorite,this.showItems=!1,this.showSearch=!1,this.isFavorite==!1&&(this.showItems=!0)},toggleList(){this.showItems=!this.showItems,this.isFavorite=!1,this.showSearch=!1},toggleSearch(){this.showSearch=!this.showSearch,this.isFavorite=!1,this.showItems=!1,this.showSearch==!1&&(this.showItems=!0)},addToFavorites(t){var e;const a=(e=this.aukstructures.find(n=>n.id===t))==null?void 0:e.title;V.post(q+"/api/favorites/add",{course_id:t,title:a}).then(n=>{this.getFavorites()}).catch(n=>{})},getFavorites(){V.get(q+"/api/favorites/").then(t=>{this.favorites=t.data.favorites})},removeFavorite(t){V.delete(q+`/api/favorites/${t}`).then(()=>{this.getFavorites()})},alertFalse(){this.alert=!1},async search(){const t={query:this.searchTerm,path:this.path,aircraft:this.aircraft};if(this.searchTerm.length<3){this.snackbarText="..не меньше трех символов",this.alertType="error",this.alert=!0;return}V.post(q+"/api/search-files/",t).then(a=>{this.matchingFiles=a.data}).catch(a=>{console.log(a)}).finally(()=>{})}}},xs={class:"text-center",style:{fontSize:"20px"}},ws={key:0,class:"ml-2 mr-2 search-files__total-results"},Ss={key:1,class:"ml-5 mr-5 mt-1 search-files__no-results"},ks=["onMouseover","onMouseleave","id","onClick"],As={id:"iframe-container",style:{"border-radius":"8px"}},Is=["src"];function Fs(t,a,e,n,r,i){const o=j("v-progress-linear"),s=j("v-sheet"),l=j("v-col"),u=j("v-icon"),d=j("v-row"),m=j("font-awesome-icon"),b=j("v-text-field"),p=j("v-btn"),k=j("v-btn-group"),y=j("v-divider"),P=j("v-card"),g=j("popup");return E(),M(gt,null,[r.isLoading?(E(),Tt(o,{key:0,color:"primary",indeterminate:""})):ot("",!0),H(" "+O(e.idEdit)+"--"+O(e.idCategory)+" ",1),a[4]||(a[4]=R("link",{rel:"stylesheet",href:"https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"},null,-1)),H(" "+O(r.link)+" "+O(r.searchTerm)+"-- "+O(r.isLoading)+" ",1),I(P,{color:"#f5f5f5"},{default:_(()=>[I(d,{dense:"","no-gutters":""},{default:_(()=>[I(l,{cols:"3"},{default:_(()=>[I(s,{class:"my-sheet pa-2 mt-1",color:"#f5f5f5",style:{overflow:"auto","overflow-y":"auto"}},{default:_(()=>[I(s,{class:"mx-auto mt-0 mb-3",elevation:"4",rounded:"lg"},{default:_(()=>[R("div",xs,O(r.titleauk.toUpperCase()),1)]),_:1}),I(d,{"no-gutters":"",align:"center "},{default:_(()=>[I(l,{cols:"1",class:"row-with-line"}),I(l,{cols:"4",class:"d-flex align-center"},{default:_(()=>[I(u,{size:"x-large",class:jt(["icon-list",{active:r.showItems}]),onClick:i.toggleList},{default:_(()=>[H(O(r.showItems?"mdi-view-list":"mdi-view-list-outline"),1)]),_:1},8,["class","onClick"]),I(u,{size:"x-large",class:jt(["icon-favorite",{active:r.isFavorite}]),onClick:i.toggleFavorite},{default:_(()=>[H(O(r.isFavorite?"mdi-heart":"mdi-heart-outline"),1)]),_:1},8,["class","onClick"]),I(u,{size:"x-large",class:jt(["icon-search",{active:r.showSearch}]),onClick:i.toggleSearch},{default:_(()=>[H(O(r.showSearch?"mdi-magnify-minus-outline":"mdi-magnify"),1)]),_:1},8,["class","onClick"])]),_:1}),I(l,{cols:"6",class:"row-with-line"}),I(l,{cols:"1",class:"d-flex align-center"},{default:_(()=>[I(u,{size:"x-large",onClick:a[0]||(a[0]=c=>i.addToFavorites(r.activeId)),icon:"mdi-playlist-star",class:"addToFav"})]),_:1})]),_:1}),r.isFavorite?(E(),Tt(d,{key:0,class:"ml-1 mr-1"},{default:_(()=>[R("ul",null,[(E(!0),M(gt,null,Nt(r.favorites,c=>(E(),M("li",{key:c.id},[H(O(c.title)+" ",1),I(m,{icon:"times",onClick:x=>i.removeFavorite(c.course_id)},null,8,["onClick"])]))),128))])]),_:1})):ot("",!0),r.showSearch?(E(),Tt(d,{key:1},{default:_(()=>[I(b,{class:"ml-5 mr-5",loading:r.loading,density:"compact",modelValue:r.searchTerm,"onUpdate:modelValue":a[1]||(a[1]=c=>r.searchTerm=c),variant:"outlined",rounded:"","append-inner-icon":"mdi-magnify",label:"Поиск","onClick:appendInner":i.search,onKeyup:hn(i.search,["enter"]),hint:"Введи искомый текст для поиска",clearable:"","single-line":""},null,8,["loading","modelValue","onClick:appendInner","onKeyup"]),R("div",null,[r.matchingFiles.length>0?(E(),M("ul",ws,[H("Всего найдено: "+O(r.matchingFiles.length)+" ",1),I(k,null,{default:_(()=>[I(p,{onClick:i.scrollToPrev},{default:_(()=>[...a[2]||(a[2]=[R("span",null,"▲",-1)])]),_:1},8,["onClick"]),I(p,{onClick:i.scrollToNext},{default:_(()=>[...a[3]||(a[3]=[R("span",null,"▼",-1)])]),_:1},8,["onClick"])]),_:1}),I(y),(E(!0),M(gt,null,Nt(r.matchingFiles,c=>(E(),M("li",{key:c.file,style:{"white-space":"nowrap"}},[I(p,{onClick:x=>i.loadContent(c.itemId,c.highlightedNodes),class:"text-truncate",style:{"max-width":"100%",overflow:"hidden","text-overflow":"ellipsis"}},{default:_(()=>[H(O(c.title),1)]),_:2},1032,["onClick"])]))),128))])):(E(),M("p",Ss,"Нет результатов"))])]),_:1})):ot("",!0),r.showItems?(E(!0),M(gt,{key:2},Nt(r.aukstructures,(c,x)=>(E(),M("div",{key:c.parent_id},[R("div",{class:"mt-1 mx-3",style:ba([c.type!==3?{cursor:"default",opacity:".7",color:"green"}:{cursor:"pointer"},{fontSize:`${-5*c.type+30}px`,paddingLeft:`${(c.type-1)*10}px`,display:"inline-block",wordWrap:"break-word"}])},[x!==0?(E(),M("div",{key:0,onMouseover:S=>c.type===3?i.showthumb(c.id):"",onMouseleave:S=>i.hidethumb(c.id),id:c.id,onClick:S=>c.type===3?i.getlink(c.id):""},O(c.title),41,ks)):ot("",!0)],4)]))),128)):ot("",!0)]),_:1})]),_:1}),I(l,{cols:"9"},{default:_(()=>[I(s,{rounded:"",elevation:"5",class:"my-sheet pa-2 mt-2 mr-2",style:{"border-radius":"8px",overflow:"auto","overflow-y":"auto"}},{default:_(()=>[R("div",As,[R("iframe",{class:"hello px-5",src:r.link,ref:"myIframe",name:"iframe_a",onload:"this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';",style:ba(r.contentStyleObj),width:"100%",scrolling:"auto"},null,12,Is)])]),_:1})]),_:1})]),_:1})]),_:1}),I(g,{alert:r.alert,alertType:r.alertType,snackbarText:r.snackbarText,overlay:r.alert,alertFalse:i.alertFalse},null,8,["alert","alertType","snackbarText","overlay","alertFalse"])],64)}const _s=mn(ys,[["render",Fs]]);export{_s as default};
