import http from './api'

export const getEditingTasksApi = params => http.get('/v1/editing-tasks', { params })

export const assignEditingTaskApi = (id, assignedUserId) =>
  http.post(`/v1/editing-tasks/${id}/assign`, { assigned_user_id: assignedUserId })

export const startEditingTaskApi = id => http.post(`/v1/editing-tasks/${id}/start`)

export const markEditingTaskInReviewApi = id => http.post(`/v1/editing-tasks/${id}/in-review`)

export const requestEditingTaskRevisionApi = id => http.post(`/v1/editing-tasks/${id}/request-revision`)

export const completeEditingTaskApi = id => http.post(`/v1/editing-tasks/${id}/complete`)
