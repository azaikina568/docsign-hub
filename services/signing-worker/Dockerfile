# Multi-stage: собираем статический бинарь, кладём в distroless (без shell/пакетов) под non-root.
FROM golang:1.23-alpine AS build
WORKDIR /src

# Сначала только манифесты — слой с зависимостями кэшируется, пока go.mod/go.sum не менялись.
COPY go.mod go.sum ./
RUN go mod download

COPY . .
# CGO off → полностью статический бинарь; -trimpath/-s -w убирают пути и отладочные символы.
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/signing-worker .

FROM gcr.io/distroless/static-debian12:nonroot
COPY --from=build /out/signing-worker /signing-worker
EXPOSE 8090
USER nonroot:nonroot
ENTRYPOINT ["/signing-worker"]
