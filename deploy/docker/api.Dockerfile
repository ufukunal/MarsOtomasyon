# syntax=docker/dockerfile:1.7

FROM mcr.microsoft.com/dotnet/sdk:10.0.12-noble AS build
WORKDIR /src

COPY Directory.Build.props ./
COPY src ./src

RUN dotnet restore src/Mars.Api/Mars.Api.csproj
RUN dotnet publish src/Mars.Api/Mars.Api.csproj \
    --configuration Release \
    --no-restore \
    --output /out

FROM mcr.microsoft.com/dotnet/aspnet:10.0.12-noble AS runtime
WORKDIR /app

ENV ASPNETCORE_URLS=http://+:8080 \
    DOTNET_EnableDiagnostics=0

COPY --from=build --chown=$APP_UID:$APP_UID /out ./

USER $APP_UID

EXPOSE 8080

ENTRYPOINT ["dotnet", "Mars.Api.dll"]
