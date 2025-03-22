<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>last.fm Discover (Unofficial)</title>
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --lastfm-red: #D51007;
      --dark-bg: #111;
      --muted-text: #666;
      --yellowish: rgb(158 142 96);
      --text: #fff;
    }

    .new-artist-message {
      align-items: center;
      color: #4fd1c5;
      font-size: 0.8rem;
      display: flex;
      gap: 6px;
      margin-top: 0.5rem;
      align-items: start;
    }

    body {
      font-family: 'Barlow', sans-serif;
      background-color: var(--dark-bg);
      color: var(--text);
      margin: 0;
      padding: 20px;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }

    .title-group {
      text-align: left;
    }

    .title {
      font-size: 24px;
      margin: 0;
      color: var(--lastfm-red);
    }

    .subtitle {
      color: var(--muted-text);
      margin-top: 0.5rem;
    }

    .controls {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 0.5rem;
    }

    .recommendations {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-top: 2rem;
    }

    .artist-card {
      background: #222;
      border-radius: 8px;
      overflow: hidden;
      transition: transform 0.2s;
    }

    .artist-card:hover {
      transform: translateY(-4px);
    }

    .artist-image {
      width: 100%;
      height: 280px;
      object-fit: cover;
      background-color: #333;
      transform: scale(1.1);
      transition: transform 0.2s;
      z-index: 0;
      position: relative;
    }

    /* .artist-card:hover .artist-image {
      transform: scale(1);
    } */

    .artist-content {
      background: #222;
      padding: 1rem;
      z-index: 1;
      position: relative;
    }

    .artist-name {
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    .artist-stats {
      color: var(--muted-text);
      font-size: 0.9rem;
      margin-bottom: 1rem;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .artist-stats > * {
      display: flex;
      align-items: flex-start;
      gap: 6px;
    }

    .artist-stats svg {
      min-width: 15px;
      min-height: 15px;
      margin-top: 2px;
    }

    .artist-summary {
      color: var(--text);
      font-size: 0.9rem;
      margin-bottom: 1rem;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .artist-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .tag {
      background: #161616;
      color: var(--text);
      padding: 0.25rem 0.75rem;
      border-radius: 1rem;
      font-size: 0.8rem;
      text-transform: lowercase;
    }

    .artist-link {
      text-decoration: none;
      color: inherit;
    }

    .refresh-button {
      padding: 0.5rem 1rem;
      background: var(--lastfm-red);
      color: var(--text);
      border: none;
      border-radius: 4px;
      font-family: 'Barlow', sans-serif;
      font-size: 0.875rem;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .refresh-button:hover {
      opacity: 0.9;
    }

    .loader {
      text-align: center;
      color: var(--muted-text);
      grid-column: 1 / -1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 200px;
      gap: 1rem;
    }

    .progress-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
      margin-top: 1rem;
      width: 200px;
    }

    .progress-bar {
      width: 200px;
      height: 4px;
      background: #333;
      border-radius: 2px;
      overflow: hidden;
    }

    .progress {
      width: 0%;
      height: 100%;
      background-color: var(--lastfm-red);
      transition: width 0.3s ease;
    }

    #progress-text {
      font-size: 0.875rem;
      color: var(--muted-text);
    }

    .error-message {
      text-align: center;
      color: var(--muted-text);
      grid-column: 1 / -1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      padding: 2rem;
    }

    .retry-button {
      padding: 0.5rem 1rem;
      background: var(--lastfm-red);
      color: var(--text);
      border: none;
      border-radius: 4px;
      font-family: 'Barlow', sans-serif;
      font-size: 0.875rem;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .retry-button:hover {
      opacity: 0.9;
    }

    .match-reason {
      color: var(--yellowish);
      font-size: 0.8rem;
      display: block;
      margin-top: 0.5rem;
    }

    .match-reason svg {
      transform: translate(-1px, 2px);
    }

    .footer {
      text-align: center;
      color: var(--muted-text);
      padding: 2rem 0;
      margin-top: 3rem;
      font-size: 0.875rem;
      border-top: 1px solid #222;
      display: grid;
      gap: 10px;
    }

    .footer p {
      margin: 0;
    }

    .footer a {
      color: var(--lastfm-red);
      text-decoration: none;
    }

    .footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="title-group">
        <h1 class="title" style="display: inline-flex; align-items: center; gap: 16px;">
          <svg fill="currentColor" width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path d="M14.131 22.948l-1.172-3.193c0 0-1.912 2.131-4.771 2.131-2.537 0-4.333-2.203-4.333-5.729 0-4.511 2.276-6.125 4.515-6.125 3.224 0 4.245 2.089 5.125 4.772l1.161 3.667c1.161 3.561 3.365 6.421 9.713 6.421 4.548 0 7.631-1.391 7.631-5.068 0-2.968-1.697-4.511-4.844-5.244l-2.344-0.511c-1.624-0.371-2.104-1.032-2.104-2.131 0-1.249 0.985-1.984 2.604-1.984 1.767 0 2.704 0.661 2.865 2.24l3.661-0.444c-0.297-3.301-2.584-4.656-6.323-4.656-3.308 0-6.532 1.251-6.532 5.245 0 2.5 1.204 4.077 4.245 4.807l2.484 0.589c1.865 0.443 2.484 1.224 2.484 2.287 0 1.359-1.323 1.921-3.828 1.921-3.703 0-5.244-1.943-6.124-4.625l-1.204-3.667c-1.541-4.765-4.005-6.531-8.891-6.531-5.287-0.016-8.151 3.385-8.151 9.192 0 5.573 2.864 8.595 8.005 8.595 4.14 0 6.125-1.943 6.125-1.943z"/>
          </svg>
          Last.fm recommendations
        </h1>
      </div>
    </div>
    <div id="recommendations" class="recommendations">
      <div class="loader">
        <div>Loading recommendations...</div>
        <div class="progress-container">
          <div class="progress-bar">
            <div class="progress" id="progress"></div>
          </div>
          <div id="progress-text">0%</div>
        </div>
      </div>
    </div>
  </div>

  <template id="artist-template">
    <a href="" class="artist-link" target="_blank">
      <div class="artist-card">
        <img class="artist-image">
        <div class="artist-content">
          <div class="artist-name"></div>
          <div class="artist-stats"></div>
          <div class="artist-summary"></div>
          <div class="artist-tags"></div>
        </div>
      </div>
    </a>
  </template>

  <script>
  function formatNumberEU(num) {
    return new Intl.NumberFormat('en-US', {
      maximumFractionDigits: 0,
      useGrouping: true,
      grouping: [3],
    }).format(num).replace(/,/g, ' ');
  }

  async function fetchRecommendations() {
    const template = document.getElementById('artist-template');
    if (!template) {
      console.error('Template element not found');
      return;
    }

    const progress = document.getElementById('progress');
    const progressText = document.getElementById('progress-text');
    const container = document.getElementById('recommendations');

    // Reset container and progress if needed
    if (!progress || !progressText) {
      container.innerHTML = `
        <div class="loader">
          <div>Loading recommendations...</div>
          <div class="progress-container">
            <div class="progress-bar">
              <div class="progress" id="progress"></div>
            </div>
            <div id="progress-text">0%</div>
          </div>
        </div>
      `;
      return fetchRecommendations();
    }

    let progressValue = 0;
    const progressInterval = setInterval(() => {
      if (progressValue < 90) {
        // Slower at the beginning, faster towards 90%
        const increment = Math.max(1, Math.floor((90 - progressValue) / 20));
        progressValue += increment;
        progress.style.width = `${progressValue}%`;
        progressText.textContent = `${progressValue}%`;
      }
    }, 600);

    try {
      const response = await fetch('api.php');
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Failed to fetch recommendations');
      }

      const recommendations = data.recommendations;

      clearInterval(progressInterval);
      progress.style.width = '100%';
      progressText.textContent = '100%';

      setTimeout(() => {
        container.innerHTML = '';
        recommendations.forEach(artist => {
          const clone = template.content.cloneNode(true);
          const link = clone.querySelector('.artist-link');
          const img = clone.querySelector('.artist-image');

          link.href = artist.url;

          if (artist.image) {
            console.log('Setting image for', artist.name, ':', artist.image);
            img.src = artist.image;
            img.alt = artist.name;
            img.onerror = () => {
              console.log('Image failed to load for', artist.name);
              img.remove();
              const placeholder = document.createElement('div');
              placeholder.className = 'artist-image';
              placeholder.style.display = 'flex';
              placeholder.style.alignItems = 'center';
              placeholder.style.justifyContent = 'center';
              placeholder.style.backgroundColor = '#e5e7eb';
              placeholder.style.fontSize = '2rem';
              placeholder.style.fontWeight = 'bold';
              placeholder.textContent = artist.name[0].toUpperCase();
              link.querySelector('.artist-card').insertBefore(placeholder, link.querySelector('.artist-content'));
            };
          } else {
            console.log('No image for', artist.name);
            // If no image, remove the img element and add a placeholder
            img.remove();
            const placeholder = document.createElement('div');
            placeholder.className = 'artist-image';
            placeholder.style.display = 'flex';
            placeholder.style.alignItems = 'center';
            placeholder.style.justifyContent = 'center';
            placeholder.style.backgroundColor = '#e5e7eb';
            placeholder.textContent = artist.name[0].toUpperCase();
            link.querySelector('.artist-card').insertBefore(placeholder, link.querySelector('.artist-content'));
          }
          clone.querySelector('.artist-name').textContent = artist.name;
          clone.querySelector('.artist-stats').innerHTML =
            `<div>${formatNumberEU(artist.listeners)} listeners • ${formatNumberEU(artist.playcount)} plays</div>` +
            (artist.isKnown ?
              `<div>${formatNumberEU(artist.userplaycount)} plays by you</div>` +
              (artist.lastplayed ?
                `<div>Last played ${new Date(parseInt(artist.lastplayed) * 1000).toLocaleDateString('en-GB', {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric'
                })}</div>` :
                '') :
              `<div class="new-artist-message">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M2 20h2c.55 0 1-.45 1-1v-9c0-.55-.45-1-1-1H2v11zm19.83-7.12c.11-.25.17-.52.17-.8V11c0-1.1-.9-2-2-2h-5.5l.92-4.65c.05-.22.02-.46-.08-.66-.23-.45-.52-.86-.88-1.22L14 2 7.59 8.41C7.21 8.79 7 9.3 7 9.83v7.84C7 18.95 8.05 20 9.34 20h8.11c.7 0 1.36-.37 1.72-.97l2.66-6.15z"/>
                </svg>
                ${artist.userplaycount > 0
                  ? `This artist is new to you, only ${formatNumberEU(artist.userplaycount)} plays - give them a spin!`
                  : 'You have not listened to this artist before, give them a spin!'
                }
              </div>`) +
            `<div class="match-reason">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
              We chose this because ${
                artist.isKnown ?
                (artist.lastplayed ?
                  `you haven't played this artist since ${new Date(parseInt(artist.lastplayed) * 1000).toLocaleDateString('en-GB', {
                    month: 'long',
                    year: 'numeric'
                  })}` :
                  'you already like this artist') :
                'it\'s similar to artists you like'
              } (${Math.round(artist.match * 100)}% match)
            </div>`;
          clone.querySelector('.artist-summary').textContent = artist.summary;

          const tagsContainer = clone.querySelector('.artist-tags');
          artist.tags.forEach(tag => {
            const span = document.createElement('span');
            span.className = 'tag';
            span.textContent = tag;
            tagsContainer.appendChild(span);
          });

          container.appendChild(clone);
        });
      }, 500);
    } catch (error) {
      const container = document.getElementById('recommendations');
      if (container) {
        let errorMessage = error.message || 'Please try again later.';

        container.innerHTML = `
          <div class="error-message">
            <div style="margin-bottom: 1rem;">${errorMessage}</div>
            <button onclick="location.reload()" class="retry-button">Retry</button>
          </div>
        `;
      }
      console.error('Recommendation fetch error:', error);
    }
  }

  // Start loading when page loads
  fetchRecommendations();
  </script>

  <footer class="footer">
    <p>This tool is not affiliated with Last.fm in any way.</p>
    <p><a href="https://github.com/ronilaukkarinen/lastfm-recommendations" target="_blank" rel="noopener noreferrer">View source on GitHub</a></p>
  </footer>
</body>
</html>
