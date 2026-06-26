import { setupServer } from 'msw/node'

// Глобальный MSW-сервер для node-тестов: общие хендлеры пусты, каждый тест добавляет свои через server.use().
export const server = setupServer()
