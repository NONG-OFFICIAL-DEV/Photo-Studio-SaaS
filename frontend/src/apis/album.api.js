import http from './api'

export const getAlbumsApi = params => http.get('/v1/albums', { params })

export const getAlbumApi = id => http.get(`/v1/albums/${id}`)

export const createAlbumApi = payload => http.post('/v1/albums', payload)

export const updateAlbumApi = (id, payload) => http.put(`/v1/albums/${id}`, payload)

export const deleteAlbumApi = id => http.delete(`/v1/albums/${id}`)

export const startAlbumApi = id => http.post(`/v1/albums/${id}/start`)

export const markAlbumReadyApi = id => http.post(`/v1/albums/${id}/ready`)

export const deliverAlbumApi = id => http.post(`/v1/albums/${id}/deliver`)

export const archiveAlbumApi = id => http.post(`/v1/albums/${id}/archive`)
