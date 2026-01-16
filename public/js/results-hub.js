import { initRankingPage } from './ranking.js';

const initHub = () => {
  const summaryContainer = document.getElementById('resultsHubSummary');
  if (typeof window.initSummaryPage === 'function') {
    window.initSummaryPage({
      resultsContainer: summaryContainer,
      autoShowResults: true,
      resultsViewMode: 'hub',
    });
  }
  initRankingPage();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initHub);
} else {
  initHub();
}
