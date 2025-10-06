import axios from 'axios';
import Dropzone from 'dropzone';
import 'dropzone/dist/dropzone.css';

Dropzone.autoDiscover = false;

const bytes = (n) => {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  if (n < 1024 * 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`;
  return `${(n / (1024 * 1024 * 1024)).toFixed(1)} GB`;
};

async function processFile(file, onProgress) {
  const chunkSize = 5 * 1024 * 1024; // 5MB chunks
  const totalSize = file.size;

  const startRes = await axios.post('/api/uploads/start', {
    filename: file.name,
    totalSize: totalSize,
    chunkSize: chunkSize,
    mime: file.type || undefined,
  });
  const uploadId = startRes.data.id;
  const totalChunks = Math.ceil(totalSize / chunkSize);

  for (let i = 0; i < totalChunks; i++) {
    const start = i * chunkSize;
    const end = Math.min(start + chunkSize, totalSize);
    const blob = file.slice(start, end);

    const form = new FormData();
    // give the chunk a filename to satisfy UploadedFile validation
    const chunkFile = new File([blob], `${file.name}.part${i}`, { type: file.type || 'application/octet-stream' });
    form.append('chunk', chunkFile);

    await axios.post(`/api/uploads/${uploadId}/chunk/${i}`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (evt) => {
        const sentForChunk = evt.total ? evt.loaded / evt.total : 0;
        const overall = ((i + sentForChunk) / totalChunks) * 100;
        onProgress(Math.min(99, overall));
      },
    });
  }

  const done = await axios.post(`/api/uploads/${uploadId}/complete`);
  onProgress(100);
  return done.data; // { status, path, url, ... }
}

function boot() {
  const zoneEl = document.getElementById('dropzone');
  const listEl = document.getElementById('upload-list');
  if (!zoneEl || !listEl) return;

  const dz = new Dropzone(zoneEl, {
    url: '/noop', // not used; we manage uploads manually
    autoProcessQueue: false,
    clickable: true,
    maxFilesize: 1024, // in MB (client hint)
    addRemoveLinks: true,
    previewsContainer: listEl,
  });

  dz.on('addedfile', async (file) => {
    const progressEl = document.createElement('div');
    progressEl.className = 'mt-2 h-2 bg-gray-200 rounded overflow-hidden';
    const bar = document.createElement('div');
    bar.className = 'h-full bg-blue-500';
    bar.style.width = '0%';
    progressEl.appendChild(bar);
    file.previewElement.appendChild(progressEl);

    try {
      const result = await processFile(file, (pct) => {
        bar.style.width = `${pct.toFixed(0)}%`;
      });
      const link = document.createElement('a');
      link.href = result.url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = 'Open file';
      link.className = 'text-blue-700 underline ml-2';
      file.previewElement.appendChild(link);
    } catch (e) {
      console.error(e);
      const err = document.createElement('div');
      err.textContent = 'Upload failed';
      err.className = 'text-red-600 mt-1';
      file.previewElement.appendChild(err);
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}

