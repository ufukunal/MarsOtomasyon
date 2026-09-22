# syntax=docker/dockerfile:1.7

FROM mcr.microsoft.com/dotnet/sdk:10.0.12-noble
WORKDIR /src

COPY .config ./.config
COPY Directory.Build.props ./
COPY src ./src

RUN dotnet tool restore
RUN dotnet restore src/Mars.Infrastructure/Mars.Infrastructure.csproj
RUN dotnet build src/Mars.Infrastructure/Mars.Infrastructure.csproj \
    --configuration Release \
    --no-restore

ENTRYPOINT ["dotnet", "tool", "run", "dotnet-ef"]
CMD ["--help"]
