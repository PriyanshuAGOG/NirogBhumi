"use strict";

(function () {
  const feed = document.querySelector("[data-static-articles-feed]");
  if (!feed) return;

  const countNode = document.querySelector("[data-static-articles-count]");
  const searchInput = document.querySelector("[data-static-articles-search]");
  const source = feed.getAttribute("data-source") || "../assets/data/education-articles.json";

  function formatDate(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
  }

  function render(items) {
    feed.innerHTML = "";
    items.forEach((item, index) => {
      const article = document.createElement("article");
      const search = `${item.title} ${item.excerpt} ${item.topic} ${item.keywords || ""}`.toLowerCase();
      article.setAttribute("data-static-article-card", "");
      article.setAttribute("data-search", search);
      article.innerHTML =
        `<small>${String(index + 1).padStart(2, "0")} | ${formatDate(item.publishedAt)} | ${item.topic}</small>` +
        `<h2><a href="${item.url}">${item.title}</a></h2>` +
        `<p>${item.excerpt}</p>` +
        `<a class="pill ghost" href="${item.url}">Read article</a>`;
      feed.appendChild(article);
    });

    if (countNode) {
      countNode.textContent = `${items.length} article${items.length === 1 ? "" : "s"} available`;
    }
  }

  function attachSearch() {
    if (!searchInput) return;
    searchInput.addEventListener("input", () => {
      const q = (searchInput.value || "").trim().toLowerCase();
      const cards = Array.from(document.querySelectorAll("[data-static-article-card]"));
      let visible = 0;
      cards.forEach((card) => {
        const show = !q || (card.getAttribute("data-search") || "").includes(q);
        card.hidden = !show;
        if (show) visible += 1;
      });
      if (countNode) {
        countNode.textContent = `${visible} article${visible === 1 ? "" : "s"} shown`;
      }
    });
  }

  fetch(source)
    .then((response) => {
      if (!response.ok) throw new Error("Unable to load education articles feed");
      return response.json();
    })
    .then((items) => {
      if (!Array.isArray(items)) throw new Error("Invalid feed format");
      render(items);
      attachSearch();
    })
    .catch(() => {
      feed.innerHTML = "<article><h2>Articles unavailable right now.</h2><p>Please check back shortly.</p></article>";
      if (countNode) countNode.textContent = "0 articles shown";
    });
})();
