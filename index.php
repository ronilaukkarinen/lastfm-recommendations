<?php
require_once 'config.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Last.fm Discover (Unofficial)</title>
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

    .next-update {
      font-size: 0.9rem;
      color: #666;
    }

    .exclude-button {
      position: absolute;
      top: 10px;
      right: 10px;
      background: rgba(0, 0, 0, 0.7);
      border: none;
      color: #fff;
      padding: 5px 10px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.8rem;
      z-index: 2;
      opacity: 0;
      transition: opacity 0.2s;
    }

    .artist-card:hover .exclude-button {
      opacity: 1;
    }

    .exclude-button:hover {
      background: rgba(213, 16, 7, 0.9);
    }

    .exclude-button[title] {
      background: rgba(213, 16, 7, 0.7);
      cursor: not-allowed;
    }

    .exclude-button[title]:hover {
      background: rgba(213, 16, 7, 0.7);
    }

    .exclude-button[title]::after {
      content: attr(title);
      position: absolute;
      bottom: 100%;
      right: 0;
      background: rgba(0, 0, 0, 0.8);
      padding: 5px 10px;
      border-radius: 4px;
      font-size: 0.8rem;
      white-space: nowrap;
      visibility: hidden;
      opacity: 0;
      transition: opacity 0.2s;
    }

    .exclude-button[title]:hover::after {
      visibility: visible;
      opacity: 1;
    }

    .artist-card.excluded {
      opacity: 0.5;
      transition: opacity 0.3s;
    }

    .artist-card.excluded:hover {
      opacity: 0.8;
    }

    .artist-card.excluded .exclude-button {
      opacity: 1;
      background: rgba(0, 0, 0, 0.5);
      cursor: not-allowed;
    }

    .artist-card.excluded .exclude-button:hover {
      background: rgba(0, 0, 0, 0.5);
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
      <div id="next-update" class="next-update"></div>
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
        <button class="exclude-button" onclick="event.preventDefault(); excludeArtist(this)">
          Recommend less
        </button>
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

  // Update the localStorage functions to handle timestamps
  function getExcludedArtists() {
    const excluded = JSON.parse(localStorage.getItem('excludedArtists') || '[]');
    const now = Date.now();
    const twoDaysMs = 2 * 24 * 60 * 60 * 1000; // 2 days in milliseconds

    // Filter out expired entries and update storage
    const validExcludes = excluded.filter(entry => {
      return (now - entry.timestamp) < twoDaysMs;
    });

    // Update storage with only valid entries
    localStorage.setItem('excludedArtists', JSON.stringify(validExcludes));

    // Return just the artist names for compatibility
    return validExcludes.map(entry => entry.name);
  }

  function addExcludedArtist(artistName) {
    const excluded = JSON.parse(localStorage.getItem('excludedArtists') || '[]');
    const now = Date.now();

    // Check if artist is already excluded
    if (!excluded.some(entry => entry.name === artistName)) {
      excluded.push({
        name: artistName,
        timestamp: now
      });
      localStorage.setItem('excludedArtists', JSON.stringify(excluded));
    }
  }

  // Add this helper function to get time remaining for exclude
  function getExcludeTimeRemaining(artistName) {
    const excluded = JSON.parse(localStorage.getItem('excludedArtists') || '[]');
    const entry = excluded.find(e => e.name === artistName);

    if (!entry) return null;

    const now = Date.now();
    const twoDaysMs = 2 * 24 * 60 * 60 * 1000;
    const remaining = twoDaysMs - (now - entry.timestamp);

    return remaining > 0 ? remaining : null;
  }

  // Update the exclude button to show remaining time on hover
  function updateExcludeButton(button, artistName) {
    const remaining = getExcludeTimeRemaining(artistName);
    if (remaining) {
      const hours = Math.floor(remaining / (60 * 60 * 1000));
      const minutes = Math.floor((remaining % (60 * 60 * 1000)) / (60 * 1000));
      button.title = `Will be available again in ${hours}h ${minutes}m`;
    }
  }

  // Update the fetchRecommendations function
  async function fetchRecommendations(refresh = false) {
    const template = document.getElementById('artist-template');
    const container = document.getElementById('recommendations');

    if (!template || !container) {
      console.error('Required elements not found');
      return;
    }

    // Show initial loading state
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

    const progress = document.getElementById('progress');
    const progressText = document.getElementById('progress-text');
    let progressValue = 0;
    let progressInterval;

    const updateProgress = (value) => {
      progressValue = value;
      if (progress) {
        progress.style.width = `${value}%`;
        progressText.textContent = `${Math.round(value)}%`;
      }
    };

    try {
      updateProgress(0);

      // Use a longer timeout for the initial load
      const timeoutDuration = refresh ? 90000 : 120000; // 120s for initial, 90s for refresh

      // Start progress animation
      const startTime = Date.now();
      const maxLoadTime = timeoutDuration - 5000; // Leave 5s buffer

      progressInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(85, (elapsed / maxLoadTime) * 100);
        updateProgress(progress);

        if (progress >= 85) {
          clearInterval(progressInterval);
        }
      }, 100);

      // Add cache-busting parameter and progress tracking
      const url = new URL(refresh ? 'api.php?refresh=1' : 'api.php', window.location.href);
      url.searchParams.append('_', Date.now()); // Cache busting

      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), timeoutDuration);

      const response = await fetch(url, {
        signal: controller.signal,
        headers: {
          'Cache-Control': 'no-cache',
          'Pragma': 'no-cache'
        }
      });

      clearTimeout(timeoutId);
      clearInterval(progressInterval);

      if (!response.ok) {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
          const errorData = await response.json();
          console.error('API Error:', errorData);
          throw new Error(errorData.error || 'Server error');
        }
        console.error('Network Error:', response.status, response.statusText);
        throw new Error(`Network error (${response.status}): ${response.statusText}`);
      }

      const data = await response.json();

      if (!data.recommendations) {
        throw new Error('Invalid response format');
      }

      updateProgress(100);

      // Short delay before showing recommendations
      await new Promise(resolve => setTimeout(resolve, 300));

      // Get excluded artists from localStorage
      const excluded = getExcludedArtists();

      // Clear the container and add recommendations
      container.innerHTML = '';

      if (Array.isArray(data.recommendations)) {
        data.recommendations.forEach(artist => {
          const clone = template.content.cloneNode(true);
          const card = clone.querySelector('.artist-card');

          const isExcluded = excluded.includes(artist.name);
          if (isExcluded) {
            card.classList.add('excluded');
            const excludeButton = card.querySelector('.exclude-button');
            excludeButton.textContent = 'Recommend less';
            excludeButton.disabled = true;
          }

          fillArtistCard(card, artist);
          container.appendChild(clone);
        });
      } else {
        throw new Error('Invalid recommendations data received');
      }

      updateNextUpdateTime();

    } catch (error) {
      if (progressInterval) {
        clearInterval(progressInterval);
      }

      console.error('Recommendation fetch error:', error);

      let errorMessage = error.message;
      let debugInfo = '';

      if (error.name === 'AbortError') {
        errorMessage = 'The request is taking longer than usual. You can try:';
      } else {
        try {
          // Try to parse the error message if it's JSON
          const errorData = JSON.parse(error.message);
          if (errorData.debug) {
            console.error('Debug info:', errorData.debug);
            debugInfo = `
              <details style="margin-top: 1rem; font-size: 0.8rem; color: #666;">
                <summary>Technical details</summary>
                <pre style="text-align: left; margin-top: 0.5rem;">${JSON.stringify(errorData.debug, null, 2)}</pre>
              </details>
            `;
          }
        } catch (e) {
          // Not JSON, use the error message as is
          debugInfo = `
            <details style="margin-top: 1rem; font-size: 0.8rem; color: #666;">
              <summary>Technical details</summary>
              <pre style="text-align: left; margin-top: 0.5rem;">${error.toString()}</pre>
            </details>
          `;
        }
      }

      container.innerHTML = `
        <div class="error-message">
          <div style="margin-bottom: 1rem;">
            ${errorMessage}
          </div>
          ${error.name === 'AbortError' ? `
            <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #666;">
              1. Refreshing the page<br>
              2. Waiting a few minutes and trying again<br>
              3. Checking if Last.fm is experiencing issues
            </div>
          ` : ''}
          ${debugInfo}
          <button onclick="fetchRecommendations(true)" class="retry-button">Retry</button>
        </div>
      `;
    }
  }

  function formatTime(hours, minutes, seconds) {
    const parts = [];

    if (hours > 0) {
      parts.push(`${hours} hour${hours !== 1 ? 's' : ''}`);
    }
    if (minutes > 0) {
      parts.push(`${minutes} minute${minutes !== 1 ? 's' : ''}`);
    }
    if (seconds > 0 || parts.length === 0) {
      parts.push(`${seconds} second${seconds !== 1 ? 's' : ''}`);
    }

    return `Next batch of recommendations in ${parts.join(', ')}`;
  }

  function updateNextUpdateTime() {
    const nextUpdateEl = document.getElementById('next-update');

    // Don't show anything while recommendations are loading
    if (document.querySelector('.loader')) {
      nextUpdateEl.textContent = '';
      return;
    }

    fetch('api.php?cache_expiry')
      .then(response => response.json())
      .then(data => {
        let expirySeconds = data.expiry;

        if (expirySeconds <= 0) {
          nextUpdateEl.textContent = 'New recommendations available';
          return;
        }

        // Update the countdown every second
        const updateCountdown = () => {
          if (expirySeconds <= 0) {
            nextUpdateEl.textContent = 'New recommendations available';
            return;
          }

          const hours = Math.floor(expirySeconds / 3600);
          const minutes = Math.floor((expirySeconds % 3600) / 60);
          const seconds = Math.floor(expirySeconds % 60);

          nextUpdateEl.textContent = formatTime(hours, minutes, seconds);
          expirySeconds--;
        };

        // Initial update
        updateCountdown();

        // Update every second
        const countdownInterval = setInterval(updateCountdown, 1000);

        // Clear interval when the page is hidden
        document.addEventListener('visibilitychange', () => {
          if (document.hidden) {
            clearInterval(countdownInterval);
          }
        });
      })
      .catch(error => {
        console.error('Error fetching cache expiry:', error);
        nextUpdateEl.textContent = '';
      });
  }

  // Add this line at the end of your script to trigger the initial load
  document.addEventListener('DOMContentLoaded', () => {
    fetchRecommendations();
  });

  // Keep the interval for regular updates
  setInterval(updateNextUpdateTime, 60000);

  // Update the excludeArtist function
  async function excludeArtist(button) {
    const artistCard = button.closest('.artist-card');
    const artistName = artistCard.querySelector('.artist-name').textContent;

    try {
      // Add to localStorage
      addExcludedArtist(artistName);

      // Update UI to show excluded state
      artistCard.classList.add('excluded');
      button.textContent = 'Recommend less';
      button.disabled = true;

    } catch (error) {
      console.error('Error excluding artist:', error);
      alert('Failed to exclude artist. Please try again.');
    }
  }

  // Update the fillArtistCard function
  function fillArtistCard(card, artist) {
    const link = card.closest('.artist-link') || card.parentElement;
    link.href = artist.url;

    // Handle image
    const img = card.querySelector('.artist-image');
    if (artist.image) {
      img.src = artist.image;
      img.alt = artist.name;
      img.onerror = () => {
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
        card.insertBefore(placeholder, card.querySelector('.artist-content'));
      };
    } else {
      img.remove();
      const placeholder = document.createElement('div');
      placeholder.className = 'artist-image';
      placeholder.style.display = 'flex';
      placeholder.style.alignItems = 'center';
      placeholder.style.justifyContent = 'center';
      placeholder.style.backgroundColor = '#e5e7eb';
      placeholder.textContent = artist.name[0].toUpperCase();
      card.insertBefore(placeholder, card.querySelector('.artist-content'));
    }

    // Fill in text content
    card.querySelector('.artist-name').textContent = artist.name;

    // Fill in stats
    const statsContainer = card.querySelector('.artist-stats');
    const statsHtml = [];

    // Add listeners and plays
    statsHtml.push(`<div>${formatNumberEU(artist.listeners)} listeners • ${formatNumberEU(artist.playcount)} plays</div>`);

    // Add user plays info
    if (artist.isKnown) {
      statsHtml.push(`<div>${formatNumberEU(artist.userplaycount)} plays by you</div>`);
      if (artist.lastplayed) {
        statsHtml.push(`<div>Last played ${new Date(parseInt(artist.lastplayed) * 1000).toLocaleDateString('en-GB', {
          day: 'numeric',
          month: 'long',
          year: 'numeric'
        })}</div>`);
      }
    } else {
      statsHtml.push(`<div class="new-artist-message">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
          <path d="M2 20h2c.55 0 1-.45 1-1v-9c0-.55-.45-1-1-1H2v11zm19.83-7.12c.11-.25.17-.52.17-.8V11c0-1.1-.9-2-2-2h-5.5l.92-4.65c.05-.22.02-.46-.08-.66-.23-.45-.52-.86-.88-1.22L14 2 7.59 8.41C7.21 8.79 7 9.3 7 9.83v7.84C7 18.95 8.05 20 9.34 20h8.11c.7 0 1.36-.37 1.72-.97l2.66-6.15z"/>
        </svg>
        ${artist.userplaycount > 0
          ? `This artist is new to you, only ${formatNumberEU(artist.userplaycount)} plays - give them a spin!`
          : 'You have not listened to this artist before, give them a spin!'
        }
      </div>`);
    }

    // Add match reason
    statsHtml.push(`<div class="match-reason">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
      </svg>
      We chose this because ${
        artist.isKnown
          ? (artist.lastplayed
            ? `you haven't played this artist since ${new Date(parseInt(artist.lastplayed) * 1000).toLocaleDateString('en-GB', {
                month: 'long',
                year: 'numeric'
              })}`
            : 'you already like this artist')
          : 'it\'s similar to artists you like'
      } (${Math.round(artist.match * 100)}% match)
    </div>`);

    statsContainer.innerHTML = statsHtml.join('');

    // Fill in summary
    card.querySelector('.artist-summary').textContent = artist.summary;

    // Fill in tags
    const tagsContainer = card.querySelector('.artist-tags');
    tagsContainer.innerHTML = '';
    artist.tags.forEach(tag => {
      const span = document.createElement('span');
      span.className = 'tag';
      span.textContent = tag;
      tagsContainer.appendChild(span);
    });
  }
  </script>

  <footer class="footer">
    <p>This tool is not affiliated with Last.fm in any way.</p>
    <p><a href="https://github.com/ronilaukkarinen/lastfm-recommendations" target="_blank" rel="noopener noreferrer">View source on GitHub</a></p>
  </footer>
</body>
</html>
