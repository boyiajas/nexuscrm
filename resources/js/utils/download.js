import axios from '../axios';

function parseFilename(disposition, fallback = 'download.csv') {
  if (!disposition) return fallback;

  const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) {
    return decodeURIComponent(utf8Match[1]);
  }

  const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
  return plainMatch?.[1] || fallback;
}

export async function downloadProtectedFile(url, fallbackName = 'download.csv') {
  const response = await axios.get(url, { responseType: 'blob' });
  const blob = new Blob([response.data], {
    type: response.headers['content-type'] || 'application/octet-stream',
  });

  const objectUrl = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = objectUrl;
  link.download = parseFilename(response.headers['content-disposition'], fallbackName);
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(objectUrl);
}
