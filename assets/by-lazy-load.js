"use strict";
var by_lz_option = by_lz_option || {},
    BY_LAZY_LOAD = {
        _ticking: !1,
        threshold: 200,
        check: function () {
            if (!BY_LAZY_LOAD._ticking) {
                BY_LAZY_LOAD._ticking = !0, void 0 !== by_lz_option.threshold && (BY_LAZY_LOAD.threshold = parseInt(by_lz_option.threshold));
                var i = document.documentElement.clientHeight || body.clientHeight,
                    c = !1,
                    e = document.getElementsByClassName("lazy-hidden");
                [].forEach.call(e, function (e, t, n) {
                    var a = e.getBoundingClientRect();
                    0 < i - a.top + BY_LAZY_LOAD.threshold && (BY_LAZY_LOAD.show(e), c = !0)
                }), BY_LAZY_LOAD._ticking = !1, c && BY_LAZY_LOAD.check()
            }
        },
        show: function (e) {
            e.className = e.className.replace(/(?:^|\s)lazy-hidden(?!\S)/g, ""), e.addEventListener("load", function () {
                e.className += " lazy-loaded", BY_LAZY_LOAD.customEvent(e, "lazyloaded")
            }, !1);
            var t = e.getAttribute("data-lazy-type");
            if ("image" == t) null != e.getAttribute("data-lazy-srcset") && e.setAttribute("srcset", e.getAttribute("data-lazy-srcset")), null != e.getAttribute("data-lazy-sizes") && e.setAttribute("sizes", e.getAttribute("data-lazy-sizes")), e.setAttribute("src", e.getAttribute("data-lazy-src"));
            else if ("iframe" == t) {
                var n = e.getAttribute("data-lazy-src"),
                    a = document.createElement("div");
                a.innerHTML = n;
                var i = a.firstChild;
                e.parentNode.replaceChild(i, e)
            }
        },
        customEvent: function (e, t) {
            var n;
            document.createEvent ? (n = document.createEvent("HTMLEvents")).initEvent(t, !0, !0) : (n = document.createEventObject()).eventType = t, n.eventName = t, document.createEvent ? e.dispatchEvent(n) : e.fireEvent("on" + n.eventType, n)
        }
    };
window.addEventListener("load", BY_LAZY_LOAD.check, !1), window.addEventListener("scroll", BY_LAZY_LOAD.check, !1), window.addEventListener("resize", BY_LAZY_LOAD.check, !1), document.getElementsByTagName("body").item(0).addEventListener("post-load", BY_LAZY_LOAD.check, !1)